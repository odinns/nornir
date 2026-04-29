<?php

declare(strict_types=1);

namespace App\Actions\Muninn;

use App\Data\Muninn\ManualAnalysisNoteResultData;
use App\Data\Shared\RecordArtifactData;
use App\Data\Shared\StartRunData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Muninn\GmailImportantEvidenceBundleReader;
use App\Services\Nornir\ArtifactRecorder;
use App\Services\Nornir\ProvenanceWriter;
use App\Services\Nornir\RunRecorder;
use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * @phpstan-import-type GmailImportantEvidenceBundle from GmailImportantEvidenceBundleReader
 * @phpstan-import-type GmailImportantEvidenceBundleItem from GmailImportantEvidenceBundleReader
 */
class BuildGmailManualAnalysisNoteAction
{
    public function __construct(
        private readonly GmailImportantEvidenceBundleReader $bundleReader,
        private readonly RunRecorder $runRecorder,
        private readonly ArtifactRecorder $artifactRecorder,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(string $bundlePath, ?string $outputPath = null): ManualAnalysisNoteResultData
    {
        $readBundle = $this->bundleReader->read($bundlePath);
        $bundlePath = $readBundle['path'];
        $bundle = $readBundle['bundle'];
        $notePath = $this->resolveOutputPath($bundle, $outputPath);

        $manualAnalysisRun = $this->runRecorder->start(new StartRunData(
            subsystem: 'muninn',
            operation: 'manual-analysis-note',
            inputScope: [
                'bundle_type' => $bundle['bundle_type'],
                'bundle_path' => $bundlePath,
                'source_run_id' => $bundle['source_run_id'],
                'evidence_run_id' => $bundle['evidence_run_id'],
                'output_path' => $notePath,
                'item_count' => count($bundle['items']),
            ],
            idempotencyKey: 'manual-analysis-note:evidence-run:'.$bundle['evidence_run_id'].':output:'.hash('sha256', $notePath),
            parentRunId: $bundle['evidence_run_id'],
        ));

        try {
            File::ensureDirectoryExists(dirname($notePath));
            File::put($notePath, $this->buildNote($bundle, $bundlePath, $manualAnalysisRun->id));

            $this->recordArtifact($manualAnalysisRun, $notePath, $bundlePath, $bundle);
            $this->writeProvenance($manualAnalysisRun, $notePath, $this->chronologyItems($bundle['items']));

            $completedRun = $this->runRecorder->complete($manualAnalysisRun);

            return new ManualAnalysisNoteResultData(
                run: $completedRun,
                notePath: $notePath,
                sourceRunId: $bundle['source_run_id'],
                evidenceRunId: $bundle['evidence_run_id'],
                itemCount: count($bundle['items']),
            );
        } catch (Throwable $throwable) {
            $this->runRecorder->fail($manualAnalysisRun, $throwable->getMessage());

            throw $throwable;
        }
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  GmailImportantEvidenceBundle  $bundle
     */
    private function resolveOutputPath(array $bundle, ?string $outputPath): string
    {
        if (is_string($outputPath) && trim($outputPath) !== '') {
            return $this->resolvePath($outputPath);
        }

        return base_path('data/reviews/operator-notes/gmail-important-manual-analysis-run-'.$bundle['evidence_run_id'].'.md');
    }

    /**
     * @param  GmailImportantEvidenceBundle  $bundle
     */
    private function buildNote(array $bundle, string $bundlePath, int $manualAnalysisRunId): string
    {
        $items = $this->chronologyItems($bundle['items']);
        $lines = [
            '# Gmail important manual analysis note',
            '',
            'Generated at: '.now()->toIso8601String(),
            'Manual analysis run id: '.$manualAnalysisRunId,
            '',
            '## Bundle metadata',
            '',
            '- Source run id: '.$bundle['source_run_id'],
            '- Evidence run id: '.$bundle['evidence_run_id'],
            '- Bundle path: '.$bundlePath,
            '- Account: '.$this->lineText($bundle['account_email']),
            '- Query: '.$this->lineText($bundle['query']),
            '- Selection: '.$this->lineText($bundle['selection']['mode']).', limit '.$bundle['selection']['limit'].', matched '.$bundle['selection']['matched_count'],
            '',
            '## Chronology candidates',
            '',
            ...$this->chronologyLines($items),
            '',
            '## Evidence by thread',
            '',
            ...$this->threadLines($items),
            '',
            '## Contradiction review',
            '',
            ...$this->contradictionLines($items),
            '',
            '## Missing context',
            '',
            ...$this->missingContextLines($items),
            '',
            '## Next action',
            '',
            ...$this->nextActionLines($items),
            '',
        ];

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     * @return list<GmailImportantEvidenceBundleItem>
     */
    private function chronologyItems(array $items): array
    {
        usort($items, static function (array $left, array $right): int {
            $receivedAtComparison = new DateTimeImmutable($left['received_at'])->getTimestamp()
                <=> new DateTimeImmutable($right['received_at'])->getTimestamp();

            if ($receivedAtComparison !== 0) {
                return $receivedAtComparison;
            }

            return strcmp($left['provenance_ref'], $right['provenance_ref']);
        });

        return $items;
    }

    /**
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     * @return list<string>
     */
    private function chronologyLines(array $items): array
    {
        if ($items === []) {
            return ['No chronology candidates in this bundle.'];
        }

        return array_map(fn (array $item): string => '- '.$this->candidateLine($item), $items);
    }

    /**
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     * @return list<string>
     */
    private function threadLines(array $items): array
    {
        if ($items === []) {
            return ['No evidence items in this bundle.'];
        }

        $lines = [];

        foreach ($this->itemsByThread($items) as $threadId => $threadItems) {
            $lines[] = '### '.$this->lineText($threadId);

            foreach ($threadItems as $item) {
                $lines[] = '- '.$this->candidateLine($item);
            }

            $lines[] = '';
        }

        array_pop($lines);

        return $lines;
    }

    /**
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     * @return array<string, list<GmailImportantEvidenceBundleItem>>
     */
    private function itemsByThread(array $items): array
    {
        $threads = [];

        foreach ($items as $item) {
            $threads[$item['thread_id']][] = $item;
        }

        ksort($threads);

        return $threads;
    }

    /**
     * @param  GmailImportantEvidenceBundleItem  $item
     */
    private function candidateLine(array $item): string
    {
        return implode(' | ', [
            $this->lineText($item['received_at']),
            '`'.$item['provenance_ref'].'`',
            $this->lineText($item['subject']),
            'from '.$this->lineText($item['from']),
            'reason: '.$this->lineText($item['reason']),
        ]);
    }

    /**
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     * @return list<string>
     */
    private function contradictionLines(array $items): array
    {
        if ($items === []) {
            return ['- [ ] No contradiction review started.'];
        }

        return array_map(
            static fn (array $item): string => '- [ ] `'.$item['provenance_ref'].'` | contradicts:',
            $items,
        );
    }

    /**
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     * @return list<string>
     */
    private function missingContextLines(array $items): array
    {
        if ($items === []) {
            return ['- [ ] No missing context reviewed.'];
        }

        return array_map(
            static fn (array $item): string => '- [ ] `'.$item['provenance_ref'].'` | missing context:',
            $items,
        );
    }

    /**
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     * @return list<string>
     */
    private function nextActionLines(array $items): array
    {
        if ($items === []) {
            return ['- [ ] No next action selected.'];
        }

        return array_map(
            fn (array $item): string => '- [ ] `'.$item['provenance_ref'].'` | '.$this->lineText($item['next_action']),
            $items,
        );
    }

    private function lineText(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));

        if ($value === '') {
            return '(blank)';
        }

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    /**
     * @param  GmailImportantEvidenceBundle  $bundle
     */
    private function recordArtifact(Run $run, string $notePath, string $bundlePath, array $bundle): void
    {
        $metadata = [
            'source_run_id' => $bundle['source_run_id'],
            'evidence_run_id' => $bundle['evidence_run_id'],
            'bundle_type' => $bundle['bundle_type'],
            'bundle_path' => $bundlePath,
            'item_count' => count($bundle['items']),
        ];

        $artifact = $this->artifactRecorder->record(new RecordArtifactData(
            runId: $run->id,
            artifactKind: 'manual-analysis-note',
            locator: $notePath,
            classification: 'review',
            metadata: $metadata,
        ));

        $artifact->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     */
    private function writeProvenance(Run $run, string $notePath, array $items): void
    {
        $run->provenanceLinks()
            ->where('output_target', 'like', $notePath.'#%')
            ->delete();

        foreach ($items as $item) {
            foreach ($this->provenanceSections($notePath, $item['provenance_ref']) as $claimKey => $outputTarget) {
                $this->provenanceWriter->link(new WriteProvenanceLinkData(
                    runId: $run->id,
                    outputTarget: $outputTarget,
                    claimKey: $claimKey,
                    evidenceType: 'canonical-row',
                    evidenceRef: $item['provenance_ref'],
                    metadata: [
                        'message_id' => $item['message_id'],
                        'thread_id' => $item['thread_id'],
                    ],
                ));
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function provenanceSections(string $notePath, string $provenanceRef): array
    {
        return [
            'chronology-candidate' => $notePath.'#chronology-candidates.'.$provenanceRef,
            'thread-evidence-item' => $notePath.'#evidence-by-thread.'.$provenanceRef,
            'contradiction-review-slot' => $notePath.'#contradiction-review.'.$provenanceRef,
            'missing-context-slot' => $notePath.'#missing-context.'.$provenanceRef,
            'next-action-slot' => $notePath.'#next-action.'.$provenanceRef,
        ];
    }
}
