<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use App\Models\GmailMessage;
use App\Models\GmailMessageObservation;
use App\Models\GmailSourceSet;
use InvalidArgumentException;

class BuildGmailSourcePageHandoffAction
{
    private const string TABLE_SOURCE_SETS = 'gmail_source_sets';

    private const string TABLE_ACCOUNTS = 'gmail_accounts';

    private const string TABLE_THREADS = 'gmail_threads';

    private const string TABLE_MESSAGES = 'gmail_messages';

    private const string TABLE_MESSAGE_OBSERVATIONS = 'gmail_message_observations';

    private const array CANONICAL_TABLES = [
        self::TABLE_SOURCE_SETS,
        self::TABLE_ACCOUNTS,
        self::TABLE_THREADS,
        self::TABLE_MESSAGES,
        'gmail_message_labels',
        'gmail_attachments',
        self::TABLE_MESSAGE_OBSERVATIONS,
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'gmail-import',
            errorMessage: 'Run does not describe a successful Gmail import.',
        );

        $run = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];
        $normalizedSourceLocator = $this->sourcePageHandoffSupport->normalizePath($sourceLocator);
        $query = (string) ($scopeSnapshot['query'] ?? '');

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
            throw new InvalidArgumentException('Gmail handoff run resolved to multiple canonical accounts.');
        }

        $accountEmail = $accountEmails[0] ?? null;
        if (! is_string($accountEmail) || $accountEmail === '') {
            throw new InvalidArgumentException('Gmail handoff run resolved to no canonical account.');
        }

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

        $threadIds = GmailMessage::query()
            ->whereIn('id', $messageRowIds)
            ->distinct()
            ->pluck('gmail_thread_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $canonicalScope = [
            'account_email' => $accountEmail,
            'query' => $query,
            'tables' => self::CANONICAL_TABLES,
            'source_set_ids' => $sourceSetIds,
            'handoff_scope' => [
                'source_set_ids' => $sourceSetIds,
                'selection_mode' => 'canonical-broad',
            ],
            'row_counts' => [
                'source_sets' => count($sourceSetIds),
                'threads' => count($threadIds),
                'messages' => count($messageRowIds),
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'gmail',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
