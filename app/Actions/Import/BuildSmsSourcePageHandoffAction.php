<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildSmsSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'sms_source_sets',
        'sms_conversations',
        'sms_participants',
        'sms_messages',
        'sms_attachments',
        'sms_message_observations',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'sms-import',
            errorMessage: 'Run does not describe a successful SMS import.',
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

        $sourceSetIds = $this->sourcePageHandoffSupport->resolveSourceSetIds('sms_source_sets', $sourceLocator);

        if ($sourceSetIds === []) {
            throw new InvalidArgumentException('No canonical SMS rows were found for the requested run.');
        }

        $messageIds = DB::table('sms_message_observations')
            ->whereIn('sms_source_set_id', $sourceSetIds)
            ->pluck('sms_message_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $conversationIds = $messageIds === []
            ? []
            : DB::table('sms_messages')
                ->whereIn('id', $messageIds)
                ->distinct()
                ->pluck('sms_conversation_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

        $participantCount = $conversationIds === []
            ? 0
            : (int) DB::table('sms_conversation_participant')
                ->whereIn('sms_conversation_id', $conversationIds)
                ->distinct()
                ->count('sms_participant_id');

        $attachmentCount = $messageIds === []
            ? 0
            : (int) DB::table('sms_attachments')
                ->whereIn('sms_message_id', $messageIds)
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
            sourceType: 'sms',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
