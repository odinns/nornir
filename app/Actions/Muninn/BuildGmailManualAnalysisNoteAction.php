<?php

declare(strict_types=1);

namespace App\Actions\Muninn;

use App\Data\Muninn\ManualAnalysisNoteResultData;
use App\Data\Shared\RecordArtifactData;
use App\Data\Shared\StartRunData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ArtifactRecorder;
use App\Services\Nornir\ProvenanceWriter;
use App\Services\Nornir\RunRecorder;
use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * @phpstan-type GmailManualAnalysisItem array{message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, received_at:string, urgency:string, reason:string, next_action:string, confidence:float|int, labels:list<string>, snippet:string, body_plain:string, body_html:string, provenance_ref:string}
 * @phpstan-type GmailManualAnalysisBundle array{schema_version:1, bundle_type:'gmail-important-mail', source_type:string, source_run_id:int, evidence_run_id:int, generated_at:string, account_email:string, source_set_ids:list<int>, query:string, selection:array{mode:string, limit:int, matched_count:int}, items:list<GmailManualAnalysisItem>}
 */
class BuildGmailManualAnalysisNoteAction
{
    public function __construct(
        private readonly RunRecorder $runRecorder,
        private readonly ArtifactRecorder $artifactRecorder,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(string $bundlePath, ?string $outputPath = null): ManualAnalysisNoteResultData
    {
        $bundlePath = $this->resolvePath($bundlePath);
        $bundle = $this->readBundle($bundlePath);
        $notePath = $this->resolveOutputPath($bundle, $outputPath);

        $this->assertRunChain($bundle);

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

    /**
     * @return GmailManualAnalysisBundle
     */
    private function readBundle(string $bundlePath): array
    {
        if (! File::exists($bundlePath)) {
            throw new InvalidArgumentException('Evidence bundle not found: '.$bundlePath);
        }

        try {
            $decoded = json_decode((string) File::get($bundlePath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Evidence bundle JSON could not be decoded: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Evidence bundle JSON must decode to an object.');
        }

        if (($decoded['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Only evidence bundle schema_version 1 is supported.');
        }

        if (($decoded['bundle_type'] ?? null) !== 'gmail-important-mail') {
            throw new InvalidArgumentException('Only gmail-important-mail evidence bundles are supported.');
        }

        $this->assertBundleShape($decoded);

        /** @var GmailManualAnalysisBundle $decoded */
        return $decoded;
    }

    /**
     * @param  array<mixed>  $bundle
     */
    private function assertBundleShape(array $bundle): void
    {
        foreach (['source_type', 'generated_at', 'account_email', 'query'] as $key) {
            if (! is_string($bundle[$key] ?? null)) {
                throw new InvalidArgumentException("Gmail evidence bundle field [{$key}] must be a string.");
            }
        }

        foreach (['source_run_id', 'evidence_run_id'] as $key) {
            if (! is_int($bundle[$key] ?? null)) {
                throw new InvalidArgumentException("Gmail evidence bundle field [{$key}] must be an integer.");
            }
        }

        if (! is_array($bundle['source_set_ids'] ?? null) || ! array_is_list($bundle['source_set_ids']) || ! $this->allIntegers($bundle['source_set_ids'])) {
            throw new InvalidArgumentException('Gmail evidence bundle field [source_set_ids] must be a list of integers.');
        }

        if (! is_array($bundle['selection'] ?? null)) {
            throw new InvalidArgumentException('Gmail evidence bundle field [selection] must be an object.');
        }

        $selection = $bundle['selection'];

        if (
            ! is_string($selection['mode'] ?? null)
            || ! is_int($selection['limit'] ?? null)
            || ! is_int($selection['matched_count'] ?? null)
        ) {
            throw new InvalidArgumentException('Gmail evidence bundle field [selection] is incomplete.');
        }

        if (! is_array($bundle['items'] ?? null) || ! array_is_list($bundle['items'])) {
            throw new InvalidArgumentException('Gmail evidence bundle field [items] must be a list.');
        }

        foreach ($bundle['items'] as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] must be an object.");
            }

            $this->assertItemShape($item, $index);
        }
    }

    /**
     * @param  array<mixed>  $item
     *
     * @phpstan-assert GmailManualAnalysisItem $item
     */
    private function assertItemShape(array $item, int $index): void
    {
        foreach (['message_id', 'thread_id', 'from', 'to', 'cc', 'subject', 'received_at', 'urgency', 'reason', 'next_action', 'snippet', 'body_plain', 'body_html', 'provenance_ref'] as $key) {
            if (! is_string($item[$key] ?? null)) {
                throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [{$key}] must be a string.");
            }
        }

        if (! is_float($item['confidence'] ?? null) && ! is_int($item['confidence'] ?? null)) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [confidence] must be numeric.");
        }

        if (! is_array($item['labels'] ?? null) || ! array_is_list($item['labels']) || ! $this->allStrings($item['labels'])) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [labels] must be a list of strings.");
        }

        $messageId = $item['message_id'] ?? null;
        $provenanceRef = $item['provenance_ref'] ?? null;

        if (! is_string($messageId) || ! is_string($provenanceRef) || $provenanceRef !== 'gmail_messages:'.$messageId) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] provenance_ref must match gmail_messages:{message_id}.");
        }

        $receivedAt = $item['received_at'] ?? null;

        if (! is_string($receivedAt)) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [received_at] must be a string.");
        }

        $this->assertReceivedAt($receivedAt, $index);
    }

    /**
     * @param  list<mixed>  $values
     */
    private function allIntegers(array $values): bool
    {
        return array_all($values, fn ($value): bool => is_int($value));
    }

    /**
     * @param  list<mixed>  $values
     */
    private function allStrings(array $values): bool
    {
        return array_all($values, fn ($value): bool => is_string($value));
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new InvalidArgumentException('Evidence bundle path is required.');
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  GmailManualAnalysisBundle  $bundle
     */
    private function resolveOutputPath(array $bundle, ?string $outputPath): string
    {
        if (is_string($outputPath) && trim($outputPath) !== '') {
            return $this->resolvePath($outputPath);
        }

        return base_path('data/reviews/operator-notes/gmail-important-manual-analysis-run-'.$bundle['evidence_run_id'].'.md');
    }

    /**
     * @param  GmailManualAnalysisBundle  $bundle
     */
    private function assertRunChain(array $bundle): void
    {
        $sourceRun = Run::query()->whereKey($bundle['source_run_id'])->first();

        if (! $sourceRun instanceof Run) {
            throw new InvalidArgumentException('Source run referenced by bundle does not exist.');
        }

        if (
            $sourceRun->subsystem !== 'import'
            || $sourceRun->operation !== 'gmail-import'
            || $sourceRun->status !== Run::STATUS_SUCCEEDED
        ) {
            throw new InvalidArgumentException('Source run referenced by bundle is not a succeeded Gmail import run.');
        }

        $evidenceRun = Run::query()->whereKey($bundle['evidence_run_id'])->first();

        if (! $evidenceRun instanceof Run) {
            throw new InvalidArgumentException('Evidence run referenced by bundle does not exist.');
        }

        if (
            $evidenceRun->subsystem !== 'muninn'
            || $evidenceRun->operation !== 'gmail-important-evidence-bundle'
            || $evidenceRun->status !== Run::STATUS_SUCCEEDED
            || $evidenceRun->parent_run_id !== $sourceRun->id
            || ($evidenceRun->input_scope['source_run_id'] ?? null) !== $sourceRun->id
        ) {
            throw new InvalidArgumentException('Evidence run referenced by bundle does not belong to the source Gmail import run.');
        }
    }

    /**
     * @param  GmailManualAnalysisBundle  $bundle
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
     * @param  list<GmailManualAnalysisItem>  $items
     * @return list<GmailManualAnalysisItem>
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
     * @param  list<GmailManualAnalysisItem>  $items
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
     * @param  list<GmailManualAnalysisItem>  $items
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
     * @param  list<GmailManualAnalysisItem>  $items
     * @return array<string, list<GmailManualAnalysisItem>>
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
     * @param  GmailManualAnalysisItem  $item
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
     * @param  list<GmailManualAnalysisItem>  $items
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
     * @param  list<GmailManualAnalysisItem>  $items
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
     * @param  list<GmailManualAnalysisItem>  $items
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
     * @param  GmailManualAnalysisBundle  $bundle
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
     * @param  list<GmailManualAnalysisItem>  $items
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

    private function assertReceivedAt(string $receivedAt, int $index): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $receivedAt) !== 1) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [received_at] must be an ISO 8601 timestamp.");
        }

        try {
            new DateTimeImmutable($receivedAt);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [received_at] must be an ISO 8601 timestamp.", $throwable->getCode(), previous: $throwable);
        }
    }
}
