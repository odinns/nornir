<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Data\Import\WikiCompilationHandoffData;
use App\Models\Run;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildChatGptSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'chatgpt_archives',
        'chatgpt_conversations',
        'chatgpt_nodes',
        'chatgpt_messages',
        'chatgpt_message_parts',
        'chatgpt_assets',
    ];

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $run = Run::query()->find($runId);

        if ($run === null
            || $run->subsystem !== 'import'
            || $run->operation !== 'chatgpt-import'
            || $run->status !== Run::STATUS_SUCCEEDED) {
            throw new InvalidArgumentException('Run does not describe a successful ChatGPT import.');
        }

        $inputScope = $run->input_scope;
        $sourceLocator = $inputScope['source_locator'] ?? null;
        $scopeSnapshot = $inputScope['scope_snapshot'] ?? [];

        if (! is_string($sourceLocator) || ! is_array($scopeSnapshot)) {
            throw new InvalidArgumentException('Run input scope is missing the ChatGPT source boundary.');
        }

        $normalizedSourceLocator = $this->normalizePath($sourceLocator);
        $normalizedAcceptedRootPaths = $this->normalizeAcceptedRootPaths(
            $scopeSnapshot['accepted_root_paths'] ?? [$sourceLocator],
        );

        $archiveIds = DB::table('chatgpt_archives')
            ->whereIn('source_locator', array_values(array_unique([$sourceLocator, $normalizedSourceLocator])))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($archiveIds === []) {
            throw new InvalidArgumentException('No canonical ChatGPT rows were found for the requested run.');
        }

        $conversationCount = (int) DB::table('chatgpt_conversations')
            ->whereIn('chatgpt_archive_id', $archiveIds)
            ->count();

        $messageCount = (int) DB::table('chatgpt_messages')
            ->join('chatgpt_conversations', 'chatgpt_conversations.id', '=', 'chatgpt_messages.chatgpt_conversation_id')
            ->whereIn('chatgpt_conversations.chatgpt_archive_id', $archiveIds)
            ->count();

        $canonicalScope = [
            'source_locator' => $normalizedSourceLocator,
            'accepted_root_paths' => $normalizedAcceptedRootPaths,
            'tables' => self::CANONICAL_TABLES,
            'archive_ids' => $archiveIds,
            'handoff_scope' => [
                'archive_ids' => $archiveIds,
            ],
            'row_counts' => [
                'archives' => count($archiveIds),
                'conversations' => $conversationCount,
                'messages' => $messageCount,
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'chatgpt',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }

    /**
     * @return list<string>
     */
    private function normalizeAcceptedRootPaths(mixed $acceptedRootPaths): array
    {
        if (! is_array($acceptedRootPaths)) {
            return [];
        }

        $paths = array_values(array_filter($acceptedRootPaths, static fn (mixed $path): bool => is_string($path) && $path !== ''));

        return array_map(
            fn (string $path): string => $this->normalizePath($path),
            $paths,
        );
    }

    private function normalizePath(string $path): string
    {
        $normalizedPath = realpath($path);

        if ($normalizedPath !== false) {
            return $normalizedPath;
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
