<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Data\Import\ChatGptImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\RecordArtifactData;
use App\Data\Shared\StartRunData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ArtifactRecorder;
use App\Services\Nornir\ProvenanceWriter;
use App\Services\Nornir\RunRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ImportChatGptConversationsAction
{
    public function __construct(
        private readonly RunRecorder $runRecorder,
        private readonly ArtifactRecorder $artifactRecorder,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload): ChatGptImportResultData
    {
        $run = $this->runRecorder->start(new StartRunData(
            subsystem: 'import',
            operation: 'chatgpt-import',
            inputScope: [
                'source_locator' => $dispatchPayload->sourceLocator,
                'scope_snapshot' => $dispatchPayload->scopeSnapshot,
            ],
            idempotencyKey: 'chatgpt-import:'.sha1($dispatchPayload->sourceLocator.'|'.json_encode($dispatchPayload->scopeSnapshot)),
        ));

        try {
            $summary = DB::transaction(fn (): array => $this->importFiles($dispatchPayload, $run));

            $this->writeArtifacts($run, $dispatchPayload, $summary);

            return new ChatGptImportResultData(
                run: $this->runRecorder->complete($run),
                summary: $summary,
            );
        } catch (Throwable $throwable) {
            $this->runRecorder->fail($run, $throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * @return array{source_file:string, conversations:int, messages:int}
     */
    private function importFiles(ImporterDispatchData $dispatchPayload, Run $run): array
    {
        $files = $this->resolveConversationFiles($dispatchPayload);

        if ($files === []) {
            throw new InvalidArgumentException('Malformed ChatGPT conversation payload: no conversation files found.');
        }

        $conversationCount = 0;
        $messageCount = 0;
        $firstFile = basename($files[0]);

        foreach ($files as $file) {
            $archive = DB::table('chatgpt_archives')->updateOrInsert(
                [
                    'archive_key' => sha1($dispatchPayload->sourceLocator.'|'.basename($file)),
                ],
                [
                    'source_locator' => $dispatchPayload->sourceLocator,
                    'source_file' => basename($file),
                    'archive_label' => $dispatchPayload->scopeSnapshot['archive_label'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            unset($archive);

            $archiveId = (int) DB::table('chatgpt_archives')
                ->where('archive_key', sha1($dispatchPayload->sourceLocator.'|'.basename($file)))
                ->value('id');

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

                foreach ($conversation['mapping'] as $node) {
                    if (! is_array($node) || ! isset($node['id']) || ! isset($node['children'])) {
                        throw new InvalidArgumentException('Malformed ChatGPT conversation payload: node is missing required keys.');
                    }

                    $nodeRowId = $this->upsertNode($conversationId, $node);

                    $message = $node['message'] ?? null;

                    if (! is_array($message) || ! isset($message['id']) || ! is_array($message['content'] ?? null)) {
                        throw new InvalidArgumentException('Malformed ChatGPT conversation payload: message is missing required keys.');
                    }

                    $messageRowId = $this->upsertMessage($conversationId, $nodeRowId, $message);
                    $messageCount++;

                    $this->syncPartsAndAssets($messageRowId, $message);

                    $this->provenanceWriter->link(new WriteProvenanceLinkData(
                        runId: $run->id,
                        outputTarget: 'chatgpt_messages:'.$message['id'],
                        claimKey: 'imported-message',
                        evidenceType: 'source-file',
                        evidenceRef: basename($file).'#message:'.$message['id'],
                    ));
                }
            }
        }

        return [
            'source_file' => $firstFile,
            'conversations' => $conversationCount,
            'messages' => $messageCount,
        ];
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

        return $files === false ? [] : array_values(array_filter($files, 'is_file'));
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
                'source_update_time' => isset($conversation['update_time']) ? (float) $conversation['update_time'] : null,
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
     */
    private function upsertMessage(int $conversationId, int $nodeId, array $message): int
    {
        $author = $message['author'] ?? [];
        $metadata = $message['metadata'] ?? [];
        $content = $message['content'] ?? [];

        DB::table('chatgpt_messages')->updateOrInsert(
            [
                'chatgpt_conversation_id' => $conversationId,
                'message_id' => (string) $message['id'],
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
                'source_update_time' => isset($message['update_time']) && is_numeric($message['update_time']) ? (float) $message['update_time'] : null,
                'end_turn' => array_key_exists('end_turn', $message) ? (bool) $message['end_turn'] : null,
                'raw_message' => json_encode($message, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('chatgpt_messages')
            ->where('chatgpt_conversation_id', $conversationId)
            ->where('message_id', (string) $message['id'])
            ->value('id');
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

            if (! is_string($assetPointer) || $assetPointer === '') {
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
     * @param  array{source_file:string, conversations:int, messages:int}  $summary
     */
    private function writeArtifacts(Run $run, ImporterDispatchData $dispatchPayload, array $summary): void
    {
        $importDirectory = base_path('data/imports/chatgpt');
        $runDirectory = base_path('data/runs/import');
        File::ensureDirectoryExists($importDirectory);
        File::ensureDirectoryExists($runDirectory);

        $slug = Str::slug(pathinfo($summary['source_file'], PATHINFO_FILENAME));
        $importSummaryPath = $importDirectory.'/'.$slug.'-summary.json';
        $runSummaryPath = $runDirectory.'/chatgpt-import-run-'.$run->id.'.json';

        $payload = [
            'source_locator' => $dispatchPayload->sourceLocator,
            'scope_snapshot' => $dispatchPayload->scopeSnapshot,
            'summary' => $summary,
        ];

        File::put($importSummaryPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        File::put($runSummaryPath, json_encode([
            'run_id' => $run->id,
            'status' => $run->status,
            'summary' => $summary,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $this->artifactRecorder->record(new RecordArtifactData(
            runId: $run->id,
            artifactKind: 'chatgpt-import-summary',
            locator: $importSummaryPath,
            classification: 'diagnostic',
            metadata: $summary,
        ));

        $this->artifactRecorder->record(new RecordArtifactData(
            runId: $run->id,
            artifactKind: 'run-summary',
            locator: $runSummaryPath,
            classification: 'diagnostic',
            metadata: $summary,
        ));
    }
}
