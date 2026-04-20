<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildAppleMessagesSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'apple_messages_source_sets',
        'apple_messages_conversations',
        'apple_messages_participants',
        'apple_messages_messages',
        'apple_messages_attachments',
        'apple_messages_message_observations',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'apple-messages-import',
            errorMessage: 'Run does not describe a successful Apple Messages import.',
        );
        $run = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];

        $normalizedSourceLocator = $this->sourcePageHandoffSupport->normalizePath($sourceLocator);
        $normalizedAcceptedRootPaths = $this->sourcePageHandoffSupport->normalizePaths($scopeSnapshot['accepted_root_paths'] ?? []);
        $attachmentsRoot = $scopeSnapshot['attachments_root'] ?? null;
        $normalizedAttachmentsRoot = is_string($attachmentsRoot)
            ? $this->sourcePageHandoffSupport->normalizePath($attachmentsRoot)
            : null;

        $sourceSetIds = $this->sourcePageHandoffSupport->resolveSourceSetIds('apple_messages_source_sets', $sourceLocator);

        if ($sourceSetIds === []) {
            throw new InvalidArgumentException('No canonical Apple Messages rows were found for the requested run.');
        }

        $messageIds = DB::table('apple_messages_message_observations')
            ->whereIn('apple_messages_source_set_id', $sourceSetIds)
            ->pluck('apple_messages_message_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $conversationIds = $messageIds === []
            ? []
            : DB::table('apple_messages_messages')
                ->whereIn('id', $messageIds)
                ->distinct()
                ->pluck('apple_messages_conversation_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

        $participantCount = $conversationIds === []
            ? 0
            : (int) DB::table('apple_messages_conversation_participant')
                ->whereIn('apple_messages_conversation_id', $conversationIds)
                ->distinct()
                ->count('apple_messages_participant_id');

        $attachmentCount = $messageIds === []
            ? 0
            : (int) DB::table('apple_messages_attachments')
                ->whereIn('apple_messages_message_id', $messageIds)
                ->count();

        $canonicalScope = [
            'source_locator' => $normalizedSourceLocator,
            'accepted_root_paths' => $normalizedAcceptedRootPaths,
            'attachments_root' => $normalizedAttachmentsRoot,
            'tables' => self::CANONICAL_TABLES,
            'source_set_ids' => $sourceSetIds,
            'handoff_scope' => [
                'source_set_ids' => $sourceSetIds,
            ],
            'row_counts' => [
                'source_sets' => count($sourceSetIds),
                'conversations' => count(array_unique($conversationIds)),
                'participants' => $participantCount,
                'messages' => count($messageIds),
                'attachments' => $attachmentCount,
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'apple-messages',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
