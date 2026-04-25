<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\ChatGptImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportChatGptConversationsAction
{
    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly SourceObservationStore $sourceObservationStore,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): ChatGptImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'chatgpt-import',
            import: fn (Run $run): array => DB::transaction(fn (): array => $this->importFiles($dispatchPayload, $run, $progress)),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->writeArtifacts($run, $dispatchPayload, $summary);
            },
        );
        /** @var array{
         *     run:Run,
         *     summary:array{
         *         source_file:string,
         *         source_set_id:int,
         *         conversations:int,
         *         messages:int,
         *         inserted_messages:int,
         *         reobserved_messages:int
         *     }
         * } $execution
         */

        return new ChatGptImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array{
     *     source_file:string,
     *     source_set_id:int,
     *     conversations:int,
     *     messages:int,
     *     inserted_messages:int,
     *     reobserved_messages:int
     * }
     */
    private function importFiles(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        $files = $this->resolveConversationFiles($dispatchPayload);

        if ($files === []) {
            throw new InvalidArgumentException('Malformed ChatGPT conversation payload: no conversation files found.');
        }

        $this->reportProgress($progress, 'files_resolved', [
            'total_files' => count($files),
        ]);

        $sourceSetId = $this->resolveSourceSetId($dispatchPayload);
        $conversationCount = 0;
        $messageCount = 0;
        $firstFile = basename($files[0]);
        $insertedMessages = 0;
        $reobservedMessages = 0;

        foreach ($files as $index => $file) {
            $this->reportProgress($progress, 'file_started', [
                'file' => basename($file),
                'current_file' => $index + 1,
                'total_files' => count($files),
                'conversations' => $conversationCount,
                'messages' => $messageCount,
            ]);

            $conversationsBeforeFile = $conversationCount;
            $messagesBeforeFile = $messageCount;

            $archiveId = $this->resolveArchiveId($sourceSetId, $dispatchPayload, $file);

            $payload = json_decode((string) file_get_contents($file), true);

            if (! is_array($payload)) {
                throw new InvalidArgumentException('Malformed ChatGPT conversation payload: top-level JSON must be an array.');
            }

            foreach ($payload as $conversation) {
                if (! $this->isValidConversation($conversation)) {
                    throw new InvalidArgumentException('Malformed ChatGPT conversation payload: conversation is missing required keys.');
                }

                $conversationId = $this->upsertConversation($archiveId, $conversation);
                $conversationCount++;
                $this->recordConversationObservation($conversationId, $sourceSetId, $archiveId);

                foreach ($conversation['mapping'] as $node) {
                    if (! is_array($node) || ! isset($node['id']) || ! isset($node['children'])) {
                        throw new InvalidArgumentException('Malformed ChatGPT conversation payload: node is missing required keys.');
                    }

                    $nodeRowId = $this->upsertNode($conversationId, $node);

                    $message = $node['message'] ?? null;

                    if ($message === null) {
                        continue;
                    }

                    if (! is_array($message) || ! isset($message['id']) || ! is_array($message['content'] ?? null)) {
                        throw new InvalidArgumentException('Malformed ChatGPT conversation payload: message is missing required keys.');
                    }

                    $messageRow = $this->upsertMessage($conversationId, $nodeRowId, $message);
                    $messageCount++;
                    if ($messageRow['wasRecentlyCreated']) {
                        $insertedMessages++;
                    } else {
                        $reobservedMessages++;
                    }

                    $this->syncPartsAndAssets($messageRow['id'], $message);
                    $this->recordMessageObservation($messageRow['id'], $sourceSetId, $archiveId);

                    $this->provenanceWriter->link(new WriteProvenanceLinkData(
                        runId: $run->id,
                        outputTarget: 'chatgpt_messages:'.$message['id'],
                        claimKey: 'imported-message',
                        evidenceType: 'source-file',
                        evidenceRef: basename($file).'#message:'.$message['id'],
                    ));
                }
            }

            $this->reportProgress($progress, 'file_completed', [
                'file' => basename($file),
                'current_file' => $index + 1,
                'total_files' => count($files),
                'file_conversations' => $conversationCount - $conversationsBeforeFile,
                'file_messages' => $messageCount - $messagesBeforeFile,
                'conversations' => $conversationCount,
                'messages' => $messageCount,
            ]);
        }

        return [
            'source_file' => $firstFile,
            'source_set_id' => $sourceSetId,
            'conversations' => $conversationCount,
            'messages' => $messageCount,
            'inserted_messages' => $insertedMessages,
            'reobserved_messages' => $reobservedMessages,
        ];
    }

    private function resolveSourceSetId(ImporterDispatchData $dispatchPayload): int
    {
        $sourceKey = sha1($dispatchPayload->accessMode.'|'.$dispatchPayload->sourceLocator.'|'.json_encode($dispatchPayload->scopeSnapshot));

        return $this->sourceObservationStore->upsertAndReturnId(
            table: 'chatgpt_source_sets',
            unique: [
                'source_key' => $sourceKey,
            ],
            values: [
                'source_locator' => $dispatchPayload->sourceLocator,
                'access_mode' => $dispatchPayload->accessMode,
            ],
        );
    }

    private function resolveArchiveId(int $sourceSetId, ImporterDispatchData $dispatchPayload, string $file): int
    {
        $archiveKey = sha1($sourceSetId.'|'.basename($file));

        return $this->sourceObservationStore->upsertAndReturnId(
            table: 'chatgpt_archives',
            unique: [
                'archive_key' => $archiveKey,
            ],
            values: [
                'chatgpt_source_set_id' => $sourceSetId,
                'source_locator' => $dispatchPayload->sourceLocator,
                'source_file' => basename($file),
                'archive_label' => $dispatchPayload->scopeSnapshot['archive_label'] ?? null,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function resolveConversationFiles(ImporterDispatchData $dispatchPayload): array
    {
        if ($dispatchPayload->accessMode === 'archive') {
            return [$dispatchPayload->sourceLocator];
        }

        $pattern = $dispatchPayload->scopeSnapshot['relative_glob'] ?? 'conversations-*.json';

        if (! is_string($pattern) || $pattern === '') {
            $pattern = 'conversations-*.json';
        }

        $files = glob(rtrim($dispatchPayload->sourceLocator, '/').'/'.$pattern);

        return $files === false ? [] : array_values(array_filter($files, is_file(...)));
    }

    private function isValidConversation(mixed $conversation): bool
    {
        return is_array($conversation)
            && isset($conversation['id'])
            && isset($conversation['mapping'])
            && is_array($conversation['mapping']);
    }

    /**
     * @param  array<string, mixed>  $conversation
     */
    private function upsertConversation(int $archiveId, array $conversation): int
    {
        DB::table('chatgpt_conversations')->updateOrInsert(
            [
                'conversation_id' => (string) $conversation['id'],
            ],
            [
                'chatgpt_archive_id' => $archiveId,
                'title' => isset($conversation['title']) ? (string) $conversation['title'] : null,
                'current_node' => isset($conversation['current_node']) ? (string) $conversation['current_node'] : null,
                'source_create_time' => isset($conversation['create_time']) ? (float) $conversation['create_time'] : null,
                'conversation_created_at' => $this->normalizeTimestamp($conversation['create_time'] ?? null),
                'source_update_time' => isset($conversation['update_time']) ? (float) $conversation['update_time'] : null,
                'conversation_updated_at' => $this->normalizeTimestamp($conversation['update_time'] ?? null),
                'raw_metadata' => json_encode($conversation, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('chatgpt_conversations')
            ->where('conversation_id', (string) $conversation['id'])
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function upsertNode(int $conversationId, array $node): int
    {
        DB::table('chatgpt_nodes')->updateOrInsert(
            [
                'chatgpt_conversation_id' => $conversationId,
                'node_id' => (string) $node['id'],
            ],
            [
                'parent_node_id' => isset($node['parent']) && is_string($node['parent']) ? $node['parent'] : null,
                'child_node_ids' => json_encode($node['children'], JSON_THROW_ON_ERROR),
                'raw_node' => json_encode($node, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('chatgpt_nodes')
            ->where('chatgpt_conversation_id', $conversationId)
            ->where('node_id', (string) $node['id'])
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{id:int, wasRecentlyCreated:bool}
     */
    private function upsertMessage(int $conversationId, int $nodeId, array $message): array
    {
        $author = $message['author'] ?? [];
        $metadata = $message['metadata'] ?? [];
        $content = $message['content'] ?? [];
        $messageId = (string) $message['id'];
        $existingMessageId = DB::table('chatgpt_messages')
            ->where('chatgpt_conversation_id', $conversationId)
            ->where('message_id', $messageId)
            ->value('id');

        DB::table('chatgpt_messages')->updateOrInsert(
            [
                'chatgpt_conversation_id' => $conversationId,
                'message_id' => $messageId,
            ],
            [
                'chatgpt_node_id' => $nodeId,
                'author_role' => is_array($author) ? ($author['role'] ?? null) : null,
                'author_name' => is_array($author) ? ($author['name'] ?? null) : null,
                'content_type' => is_array($content) ? ($content['content_type'] ?? null) : null,
                'status' => isset($message['status']) ? (string) $message['status'] : null,
                'recipient' => isset($message['recipient']) ? (string) $message['recipient'] : null,
                'model_slug' => is_array($metadata) ? ($metadata['model_slug'] ?? null) : null,
                'source_create_time' => isset($message['create_time']) && is_numeric($message['create_time']) ? (float) $message['create_time'] : null,
                'message_created_at' => $this->normalizeTimestamp($message['create_time'] ?? null),
                'source_update_time' => isset($message['update_time']) && is_numeric($message['update_time']) ? (float) $message['update_time'] : null,
                'message_updated_at' => $this->normalizeTimestamp($message['update_time'] ?? null),
                'end_turn' => array_key_exists('end_turn', $message) ? (bool) $message['end_turn'] : null,
                'raw_message' => json_encode($message, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return [
            'id' => (int) DB::table('chatgpt_messages')
                ->where('chatgpt_conversation_id', $conversationId)
                ->where('message_id', $messageId)
                ->value('id'),
            'wasRecentlyCreated' => $existingMessageId === null,
        ];
    }

    private function recordConversationObservation(int $conversationId, int $sourceSetId, int $archiveId): void
    {
        $this->sourceObservationStore->record(
            table: 'chatgpt_conversation_observations',
            unique: [
                'chatgpt_conversation_id' => $conversationId,
                'chatgpt_source_set_id' => $sourceSetId,
            ],
            values: [
                'chatgpt_archive_id' => $archiveId,
            ],
        );
    }

    private function recordMessageObservation(int $messageId, int $sourceSetId, int $archiveId): void
    {
        $this->sourceObservationStore->record(
            table: 'chatgpt_message_observations',
            unique: [
                'chatgpt_message_id' => $messageId,
                'chatgpt_source_set_id' => $sourceSetId,
            ],
            values: [
                'chatgpt_archive_id' => $archiveId,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function syncPartsAndAssets(int $messageId, array $message): void
    {
        $content = $message['content'] ?? [];
        $parts = is_array($content) && is_array($content['parts'] ?? null) ? $content['parts'] : [];

        DB::table('chatgpt_message_parts')->where('chatgpt_message_id', $messageId)->delete();
        DB::table('chatgpt_assets')->where('chatgpt_message_id', $messageId)->delete();

        $partIndex = 0;

        foreach ($parts as $part) {
            if (is_string($part)) {
                if (trim($part) === '') {
                    continue;
                }

                DB::table('chatgpt_message_parts')->insert([
                    'chatgpt_message_id' => $messageId,
                    'part_index' => $partIndex++,
                    'part_type' => 'text',
                    'text_part' => $part,
                    'asset_pointer' => null,
                    'raw_part' => json_encode(['text' => $part], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            if (! is_array($part)) {
                continue;
            }

            $assetPointer = $part['asset_pointer'] ?? null;
            if (! is_string($assetPointer)) {
                continue;
            }
            if ($assetPointer === '') {
                continue;
            }

            DB::table('chatgpt_assets')->insert([
                'chatgpt_message_id' => $messageId,
                'asset_pointer' => $assetPointer,
                'asset_type' => isset($part['content_type']) ? (string) $part['content_type'] : null,
                'raw_asset' => json_encode($part, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function writeArtifacts(Run $run, ImporterDispatchData $dispatchPayload, array $summary): void
    {
        $this->importArtifactWriter->write(
            run: $run,
            dispatchPayload: $dispatchPayload,
            sourceType: 'chatgpt',
            artifactKind: 'chatgpt-import-summary',
            summary: $summary,
        );
    }

    /**
     * @param  array<string, int|string>  $payload
     */
    private function reportProgress(?callable $progress, string $event, array $payload): void
    {
        if ($progress === null) {
            return;
        }

        $progress($event, $payload);
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((float) $value)->toDateTimeString();
    }
}
