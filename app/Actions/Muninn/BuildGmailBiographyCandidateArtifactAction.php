<?php

declare(strict_types=1);

namespace App\Actions\Muninn;

use App\Data\Muninn\BiographyCandidateArtifactResultData;
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
 *
 * @phpstan-type GmailBiographyCandidate array{candidate_id:string, candidate_type:'chronology_candidate', review_status:'unreviewed', occurred_at:string, occurred_at_basis:'gmail.received_at', provenance_ref:string, message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, selection_reason:string, snippet:string, next_action:string, confidence:float|int, labels:list<string>}
 * @phpstan-type GmailBiographyCandidateArtifact array{schema_version:1, artifact_type:'muninn-biography-candidates', generated_at:string, source_bundle_path:string, source_bundle_type:'gmail-important-mail', source_type:'gmail', source_run_id:int, evidence_run_id:int, candidate_run_id:int, candidate_count:int, candidates:list<GmailBiographyCandidate>}
 */
class BuildGmailBiographyCandidateArtifactAction
{
    public function __construct(
        private readonly GmailImportantEvidenceBundleReader $bundleReader,
        private readonly RunRecorder $runRecorder,
        private readonly ArtifactRecorder $artifactRecorder,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(string $bundlePath, ?string $outputPath = null): BiographyCandidateArtifactResultData
    {
        $readBundle = $this->bundleReader->read($bundlePath);
        $bundlePath = $readBundle['path'];
        $bundle = $readBundle['bundle'];
        $candidatePath = $this->resolveOutputPath($bundle, $outputPath);

        $candidateRun = $this->runRecorder->start(new StartRunData(
            subsystem: 'muninn',
            operation: 'gmail-biography-candidates',
            inputScope: [
                'bundle_type' => $bundle['bundle_type'],
                'bundle_path' => $bundlePath,
                'source_run_id' => $bundle['source_run_id'],
                'evidence_run_id' => $bundle['evidence_run_id'],
                'output_path' => $candidatePath,
                'item_count' => count($bundle['items']),
            ],
            idempotencyKey: 'gmail-biography-candidates:evidence-run:'.$bundle['evidence_run_id'].':output:'.hash('sha256', $candidatePath),
            parentRunId: $bundle['evidence_run_id'],
        ));

        try {
            $candidates = $this->candidates($bundle['items']);
            $artifact = $this->buildArtifact($bundle, $bundlePath, $candidateRun->id, $candidates);

            File::ensureDirectoryExists(dirname($candidatePath));
            File::put($candidatePath, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $this->recordArtifact($candidateRun, $candidatePath, $bundlePath, $bundle, count($candidates));
            $this->writeProvenance($candidateRun, $candidatePath, $candidates);

            $completedRun = $this->runRecorder->complete($candidateRun);

            return new BiographyCandidateArtifactResultData(
                run: $completedRun,
                candidatePath: $candidatePath,
                sourceRunId: $bundle['source_run_id'],
                evidenceRunId: $bundle['evidence_run_id'],
                candidateCount: count($candidates),
            );
        } catch (Throwable $throwable) {
            $this->runRecorder->fail($candidateRun, $throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * @param  GmailImportantEvidenceBundle  $bundle
     */
    private function resolveOutputPath(array $bundle, ?string $outputPath): string
    {
        if (is_string($outputPath) && trim($outputPath) !== '') {
            return $this->resolvePath($outputPath);
        }

        return base_path('data/reviews/muninn-biography-candidates-run-'.$bundle['evidence_run_id'].'.json');
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
     * @param  list<GmailImportantEvidenceBundleItem>  $items
     * @return list<GmailBiographyCandidate>
     */
    private function candidates(array $items): array
    {
        $candidates = array_map(fn (array $item): array => [
            'candidate_id' => $this->candidateId($item),
            'candidate_type' => 'chronology_candidate',
            'review_status' => 'unreviewed',
            'occurred_at' => $item['received_at'],
            'occurred_at_basis' => 'gmail.received_at',
            'provenance_ref' => $item['provenance_ref'],
            'message_id' => $item['message_id'],
            'thread_id' => $item['thread_id'],
            'from' => $item['from'],
            'to' => $item['to'],
            'cc' => $item['cc'],
            'subject' => $item['subject'],
            'selection_reason' => $item['reason'],
            'snippet' => $item['snippet'],
            'next_action' => $item['next_action'],
            'confidence' => $item['confidence'],
            'labels' => $item['labels'],
        ], $items);

        usort($candidates, static function (array $left, array $right): int {
            $occurredAtComparison = new DateTimeImmutable($left['occurred_at'])->getTimestamp()
                <=> new DateTimeImmutable($right['occurred_at'])->getTimestamp();

            if ($occurredAtComparison !== 0) {
                return $occurredAtComparison;
            }

            return strcmp($left['candidate_id'], $right['candidate_id']);
        });

        return $candidates;
    }

    /**
     * @param  GmailImportantEvidenceBundleItem  $item
     */
    private function candidateId(array $item): string
    {
        return 'chronology_candidate:'.$item['provenance_ref'];
    }

    /**
     * @param  GmailImportantEvidenceBundle  $bundle
     * @param  list<GmailBiographyCandidate>  $candidates
     * @return GmailBiographyCandidateArtifact
     */
    private function buildArtifact(array $bundle, string $bundlePath, int $candidateRunId, array $candidates): array
    {
        return [
            'schema_version' => 1,
            'artifact_type' => 'muninn-biography-candidates',
            'generated_at' => now()->toIso8601String(),
            'source_bundle_path' => $bundlePath,
            'source_bundle_type' => $bundle['bundle_type'],
            'source_type' => $bundle['source_type'],
            'source_run_id' => $bundle['source_run_id'],
            'evidence_run_id' => $bundle['evidence_run_id'],
            'candidate_run_id' => $candidateRunId,
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
        ];
    }

    /**
     * @param  GmailImportantEvidenceBundle  $bundle
     */
    private function recordArtifact(Run $run, string $candidatePath, string $bundlePath, array $bundle, int $candidateCount): void
    {
        $metadata = [
            'source_run_id' => $bundle['source_run_id'],
            'evidence_run_id' => $bundle['evidence_run_id'],
            'bundle_type' => $bundle['bundle_type'],
            'bundle_path' => $bundlePath,
            'candidate_count' => $candidateCount,
        ];

        $artifact = $this->artifactRecorder->record(new RecordArtifactData(
            runId: $run->id,
            artifactKind: 'muninn-biography-candidates',
            locator: $candidatePath,
            classification: 'review',
            metadata: $metadata,
        ));

        $artifact->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * @param  list<GmailBiographyCandidate>  $candidates
     */
    private function writeProvenance(Run $run, string $candidatePath, array $candidates): void
    {
        $run->provenanceLinks()
            ->where('output_target', 'like', $candidatePath.'#candidates.%')
            ->delete();

        foreach ($candidates as $candidate) {
            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: $candidatePath.'#candidates.'.$candidate['candidate_id'],
                claimKey: 'chronology-candidate',
                evidenceType: 'canonical-row',
                evidenceRef: $candidate['provenance_ref'],
                metadata: [
                    'message_id' => $candidate['message_id'],
                    'thread_id' => $candidate['thread_id'],
                    'occurred_at' => $candidate['occurred_at'],
                    'occurred_at_basis' => $candidate['occurred_at_basis'],
                ],
            ));
        }
    }
}
