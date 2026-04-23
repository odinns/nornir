<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  list<array<string, mixed>>  $conversations
 */
function createChatGptExportDirectory(array $conversations): string
{
    $path = storage_path('framework/testing/chatgpt-export-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($path);
    file_put_contents(
        $path.'/conversations-000.json',
        json_encode($conversations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );

    return $path;
}

/**
 * @return array<string, mixed>
 */
function buildChatGptConversation(string $conversationId = 'conversation-1'): array
{
    return [
        'id' => $conversationId,
        'conversation_id' => $conversationId,
        'title' => 'Importer test conversation',
        'create_time' => 1_697_527_962.568149,
        'update_time' => 1_697_528_705.169732,
        'current_node' => 'assistant-'.$conversationId,
        'mapping' => [
            'root-'.$conversationId => [
                'id' => 'root-'.$conversationId,
                'parent' => null,
                'children' => ['user-1-'.$conversationId],
                'message' => [
                    'id' => 'root-'.$conversationId,
                    'author' => ['role' => 'system', 'name' => null, 'metadata' => []],
                    'content' => ['content_type' => 'text', 'parts' => ['']],
                    'metadata' => ['is_visually_hidden_from_conversation' => true],
                    'status' => 'finished_successfully',
                    'recipient' => 'all',
                    'weight' => 0.0,
                    'create_time' => null,
                    'update_time' => null,
                    'end_turn' => true,
                    'channel' => null,
                ],
            ],
            'user-1-'.$conversationId => [
                'id' => 'user-1-'.$conversationId,
                'parent' => 'root-'.$conversationId,
                'children' => ['assistant-'.$conversationId],
                'message' => [
                    'id' => 'user-1-'.$conversationId,
                    'author' => ['role' => 'user', 'name' => null, 'metadata' => []],
                    'content' => ['content_type' => 'text', 'parts' => ['What time is importer time?']],
                    'metadata' => ['timestamp_' => 'absolute'],
                    'status' => 'finished_successfully',
                    'recipient' => 'all',
                    'weight' => 1.0,
                    'create_time' => 1_697_528_094.361272,
                    'update_time' => null,
                    'end_turn' => null,
                    'channel' => null,
                ],
            ],
            'assistant-'.$conversationId => [
                'id' => 'assistant-'.$conversationId,
                'parent' => 'user-1-'.$conversationId,
                'children' => ['user-2-'.$conversationId],
                'message' => [
                    'id' => 'assistant-'.$conversationId,
                    'author' => ['role' => 'assistant', 'name' => null, 'metadata' => []],
                    'content' => [
                        'content_type' => 'multimodal_text',
                        'parts' => [
                            'It is importer o clock.',
                            ['asset_pointer' => 'file-service://file-asset-1', 'content_type' => 'image_asset_pointer'],
                            'Still a terrible time for optimism.',
                        ],
                    ],
                    'metadata' => [
                        'timestamp_' => 'absolute',
                        'model_slug' => 'gpt-4',
                    ],
                    'status' => 'finished_successfully',
                    'recipient' => 'all',
                    'weight' => 1.0,
                    'create_time' => 1_697_528_122.017504,
                    'update_time' => null,
                    'end_turn' => true,
                    'channel' => null,
                ],
            ],
            'user-2-'.$conversationId => [
                'id' => 'user-2-'.$conversationId,
                'parent' => 'assistant-'.$conversationId,
                'children' => [],
                'message' => [
                    'id' => 'user-2-'.$conversationId,
                    'author' => ['role' => 'user', 'name' => null, 'metadata' => []],
                    'content' => ['content_type' => 'text', 'parts' => ['Fine. Keep going.']],
                    'metadata' => ['timestamp_' => 'absolute'],
                    'status' => 'finished_successfully',
                    'recipient' => 'all',
                    'weight' => 1.0,
                    'create_time' => 1_697_528_705.169732,
                    'update_time' => null,
                    'end_turn' => null,
                    'channel' => null,
                ],
            ],
        ],
    ];
}
