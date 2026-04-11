<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildChatGptSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'chatgpt_archives',
        'chatgpt_source_sets',
        'chatgpt_conversations',
        'chatgpt_nodes',
        'chatgpt_messages',
        'chatgpt_message_parts',
        'chatgpt_assets',
        'chatgpt_conversation_observations',
        'chatgpt_message_observations',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'chatgpt-import',
            errorMessage: 'Run does not describe a successful ChatGPT import.',
        );
        $run = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];

        $normalizedSourceLocator = $this->sourcePageHandoffSupport->normalizePath($sourceLocator);
        $normalizedAcceptedRootPaths = $this->sourcePageHandoffSupport->normalizePaths(
            $scopeSnapshot['accepted_root_paths'] ?? [$sourceLocator],
        );

        $sourceSetIds = $this->sourcePageHandoffSupport->resolveSourceSetIds('chatgpt_source_sets', $sourceLocator);

        if ($sourceSetIds === []) {
            throw new InvalidArgumentException('No canonical ChatGPT rows were found for the requested run.');
        }

        $conversationIds = DB::table('chatgpt_conversation_observations')
            ->whereIn('chatgpt_source_set_id', $sourceSetIds)
            ->pluck('chatgpt_conversation_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $messageIds = DB::table('chatgpt_message_observations')
            ->whereIn('chatgpt_source_set_id', $sourceSetIds)
            ->pluck('chatgpt_message_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $canonicalScope = [
            'source_locator' => $normalizedSourceLocator,
            'accepted_root_paths' => $normalizedAcceptedRootPaths,
            'tables' => self::CANONICAL_TABLES,
            'source_set_ids' => $sourceSetIds,
            'handoff_scope' => [
                'source_set_ids' => $sourceSetIds,
            ],
            'row_counts' => [
                'source_sets' => count($sourceSetIds),
                'conversations' => count(array_unique($conversationIds)),
                'messages' => count(array_unique($messageIds)),
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'chatgpt',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
