<?php

declare(strict_types=1);

namespace App\Actions\Gmail;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Gmail\GmailImportantEvidenceBundleResultData;
use App\Data\Shared\RecordArtifactData;
use App\Data\Shared\StartRunData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\GmailMessage;
use App\Models\GmailMessageObservation;
use App\Models\GmailSourceSet;
use App\Models\Run;
use App\Services\Gmail\GmailImportantMailScorer;
use App\Services\Nornir\ArtifactRecorder;
use App\Services\Nornir\ProvenanceWriter;
use App\Services\Nornir\RunRecorder;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Throwable;

/**
 * @phpstan-import-type ScoredGmailImportantMailItem from GmailImportantMailScorer
 *
 * @phpstan-type GmailImportantEvidenceItem array{message_id: string, thread_id: string, from: string, to: string, cc: string, subject: string, received_at: string, urgency: string, reason: string, next_action: string, confidence: float, labels: list<string>, snippet: string, body_plain: string, body_html: string, provenance_ref: string}
 * @phpstan-type GmailImportantEvidenceBundle array{schema_version: 1, bundle_type: string, source_type: string, source_run_id: int, evidence_run_id: int, generated_at: string, account_email: string, source_set_ids: list<int>, query: string, selection: array{mode: string, limit: int, matched_count: int}, items: list<GmailImportantEvidenceItem>}
 */
class BuildGmailImportantEvidenceBundleAction
{
    private const int DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
        private readonly GmailImportantMailScorer $importantMailScorer,
        private readonly RunRecorder $runRecorder,
        private readonly ArtifactRecorder $artifactRecorder,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(int $runId, ?int $limit = self::DEFAULT_LIMIT, ?string $rulesPath = null): GmailImportantEvidenceBundleResultData
    {
        $limit ??= self::DEFAULT_LIMIT;

        if ($limit < 1) {
            throw new InvalidArgumentException('The limit must be at least 1.');
        }

        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'gmail-import',
            errorMessage: 'Run does not describe a successful Gmail import.',
        );

        $sourceRun = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];
        $query = (string) ($scopeSnapshot['query'] ?? '');
        $sourceSetRows = $this->resolveSourceSets($sourceLocator, $query);
        $sourceSetIds = $sourceSetRows['ids'];
        $accountEmail = $sourceSetRows['account_email'];
        $messageRowIds = $this->resolveMessageRowIds($sourceSetIds);
        $rules = $this->importantMailScorer->loadRules($rulesPath);
        $selectedItems = $this->scoreMessages($messageRowIds, $accountEmail, $rules, $limit);

        $evidenceRun = $this->runRecorder->start(new StartRunData(
            subsystem: 'muninn',
            operation: 'gmail-important-evidence-bundle',
            inputScope: [
                'source_type' => 'gmail',
                'source_run_id' => $sourceRun->id,
                'source_set_ids' => $sourceSetIds,
                'query' => $query,
                'selection' => [
                    'mode' => 'important-mail-score',
                    'limit' => $limit,
                    'rules_path' => $rulesPath,
                ],
            ],
            idempotencyKey: 'gmail-important-evidence-bundle:source-run:'.$sourceRun->id,
            parentRunId: $sourceRun->id,
        ));

        try {
            $path = base_path('data/reviews/gmail-important-evidence-run-'.$sourceRun->id.'.json');
            $bundle = $this->buildBundle(
                sourceRun: $sourceRun,
                evidenceRun: $evidenceRun,
                accountEmail: $accountEmail,
                sourceSetIds: $sourceSetIds,
                query: $query,
                limit: $limit,
                selectedItems: $selectedItems,
            );

            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $this->recordArtifact($evidenceRun, $path, $sourceRun->id, $sourceSetIds, $limit, count($selectedItems));
            $this->writeProvenance($evidenceRun, $path, $selectedItems);

            $completedRun = $this->runRecorder->complete($evidenceRun);

            return new GmailImportantEvidenceBundleResultData(
                run: $completedRun,
                path: $path,
                sourceRunId: $sourceRun->id,
                matchedCount: count($selectedItems),
                sourceSetIds: $sourceSetIds,
            );
        } catch (Throwable $throwable) {
            $this->runRecorder->fail($evidenceRun, $throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * @return array{ids: list<int>, account_email: string}
     */
    private function resolveSourceSets(string $sourceLocator, string $query): array
    {
        $normalizedSourceLocator = $this->sourcePageHandoffSupport->normalizePath($sourceLocator);

        $sourceSetRows = GmailSourceSet::query()
            ->whereIn('source_locator', array_values(array_unique([$sourceLocator, $normalizedSourceLocator])))
            ->where('query', $query)
            ->orderBy('id')
            ->get(['id', 'account_email']);

        $sourceSetIds = $sourceSetRows
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($sourceSetIds === []) {
            throw new InvalidArgumentException('No canonical Gmail rows were found for the requested run.');
        }

        $accountEmails = $sourceSetRows
            ->pluck('account_email')
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->unique()
            ->values()
            ->all();

        if (count($accountEmails) !== 1) {
            throw new InvalidArgumentException('Gmail evidence run resolved to multiple canonical accounts.');
        }

        $accountEmail = $accountEmails[0] ?? null;

        if (! is_string($accountEmail) || $accountEmail === '') {
            throw new InvalidArgumentException('Gmail evidence run resolved to no canonical account.');
        }

        return [
            'ids' => array_values($sourceSetIds),
            'account_email' => $accountEmail,
        ];
    }

    /**
     * @param  list<int>  $sourceSetIds
     * @return list<int>
     */
    private function resolveMessageRowIds(array $sourceSetIds): array
    {
        $messageRowIds = GmailMessageObservation::query()
            ->whereIn('gmail_source_set_id', $sourceSetIds)
            ->pluck('gmail_message_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($messageRowIds === []) {
            throw new InvalidArgumentException('No canonical Gmail rows were found for the requested run.');
        }

        return array_values($messageRowIds);
    }

    /**
     * @param  list<int>  $messageRowIds
     * @param  array{priority_senders: list<string>, priority_domains: list<string>, priority_labels: list<string>, ignore_senders: list<string>, ignore_domains: list<string>, ignore_subject_keywords: list<string>}  $rules
     * @return list<ScoredGmailImportantMailItem>
     */
    private function scoreMessages(array $messageRowIds, string $accountEmail, array $rules, int $limit): array
    {
        $items = [];

        GmailMessage::query()
            ->with(['thread', 'labels'])
            ->whereIn('id', $messageRowIds)
            ->orderBy('id')
            ->chunkById(500, function ($messages) use ($accountEmail, $rules, $limit, &$items): void {
                foreach ($messages as $message) {
                    $item = $this->importantMailScorer->scoreCanonicalMessage($message, $accountEmail, $rules);

                    if ($item !== null) {
                        $items[] = $item;
                    }
                }

                $items = array_slice($this->importantMailScorer->rank($items), 0, $limit);
            });

        return $this->importantMailScorer->rank($items);
    }

    /**
     * @param  list<int>  $sourceSetIds
     * @param  list<ScoredGmailImportantMailItem>  $selectedItems
     * @return GmailImportantEvidenceBundle
     */
    private function buildBundle(
        Run $sourceRun,
        Run $evidenceRun,
        string $accountEmail,
        array $sourceSetIds,
        string $query,
        int $limit,
        array $selectedItems,
    ): array {
        return [
            'schema_version' => 1,
            'bundle_type' => 'gmail-important-mail',
            'source_type' => 'gmail',
            'source_run_id' => $sourceRun->id,
            'evidence_run_id' => $evidenceRun->id,
            'generated_at' => now()->toIso8601String(),
            'account_email' => $accountEmail,
            'source_set_ids' => $sourceSetIds,
            'query' => $query,
            'selection' => [
                'mode' => 'important-mail-score',
                'limit' => $limit,
                'matched_count' => count($selectedItems),
            ],
            'items' => array_map(static function (array $item): array {
                return [
                    'message_id' => $item['message_id'],
                    'thread_id' => $item['thread_id'],
                    'from' => $item['from'],
                    'to' => $item['to'],
                    'cc' => $item['cc'],
                    'subject' => $item['subject'],
                    'received_at' => $item['received_at'],
                    'urgency' => $item['urgency'],
                    'reason' => $item['reason'],
                    'next_action' => $item['next_action'],
                    'confidence' => $item['confidence'],
                    'labels' => $item['labels'],
                    'snippet' => $item['snippet'],
                    'body_plain' => $item['body_plain'],
                    'body_html' => $item['body_html'],
                    'provenance_ref' => 'gmail_messages:'.$item['message_id'],
                ];
            }, $selectedItems),
        ];
    }

    /**
     * @param  list<int>  $sourceSetIds
     */
    private function recordArtifact(Run $run, string $path, int $sourceRunId, array $sourceSetIds, int $limit, int $matchedCount): void
    {
        $metadata = [
            'source_run_id' => $sourceRunId,
            'source_set_ids' => $sourceSetIds,
            'selection_mode' => 'important-mail-score',
            'limit' => $limit,
            'matched_count' => $matchedCount,
        ];

        $artifact = $this->artifactRecorder->record(new RecordArtifactData(
            runId: $run->id,
            artifactKind: 'gmail-important-evidence-bundle',
            locator: $path,
            classification: 'review',
            metadata: $metadata,
        ));

        $artifact->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * @param  list<ScoredGmailImportantMailItem>  $selectedItems
     */
    private function writeProvenance(Run $run, string $path, array $selectedItems): void
    {
        $run->provenanceLinks()
            ->where('output_target', 'like', $path.'#items.%')
            ->delete();

        foreach ($selectedItems as $index => $item) {
            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: $path.'#items.'.$index,
                claimKey: 'important-mail-item',
                evidenceType: 'canonical-row',
                evidenceRef: 'gmail_messages:'.$item['message_id'],
                metadata: [
                    'message_id' => $item['message_id'],
                    'score' => $item['_score'],
                ],
            ));
        }
    }
}
