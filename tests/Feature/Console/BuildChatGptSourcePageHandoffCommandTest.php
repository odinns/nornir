<?php

declare(strict_types=1);

use App\Actions\Import\ImportChatGptConversationsAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('builds a chatgpt source-page handoff from the latest successful import run', function (): void {
    $exportRoot = createConsoleHandoffExportDirectory([
        buildConsoleHandoffConversation('handoff-cli-conversation'),
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $exportRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$exportRoot],
            'relative_glob' => 'conversations-*.json',
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportChatGptConversationsAction::class)($intake->dispatchPayload);

    artisanCommand($this, 'handoff:chatgpt-source-pages')
        ->expectsOutputToContain('Building ChatGPT source-page handoff')
        ->expectsOutputToContain("Using run id: {$importResult->run->id}")
        ->expectsOutputToContain('Source locator: '.$exportRoot)
        ->expectsOutputToContain('Source set count: 1')
        ->expectsOutputToContain('Conversation count: 1')
        ->expectsOutputToContain('Message count: 2')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});

it('can build a chatgpt source-page handoff for an explicit run id', function (): void {
    $exportRoot = createConsoleHandoffExportDirectory([
        buildConsoleHandoffConversation('handoff-cli-explicit'),
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $exportRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$exportRoot],
            'relative_glob' => 'conversations-*.json',
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportChatGptConversationsAction::class)($intake->dispatchPayload);

    artisanCommand($this, 'handoff:chatgpt-source-pages', [
        '--run-id' => $importResult->run->id,
    ])->assertSuccessful();
});

/**
 * @param  list<array<string, mixed>>  $conversations
 */
function createConsoleHandoffExportDirectory(array $conversations): string
{
    $path = storage_path('framework/testing/chatgpt-handoff-cli-'.bin2hex(random_bytes(4)));
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
function buildConsoleHandoffConversation(string $conversationId): array
{
    return [
        'id' => $conversationId,
        'conversation_id' => $conversationId,
        'title' => 'Console handoff conversation',
        'create_time' => 1_697_527_962.568149,
        'update_time' => 1_697_528_705.169732,
        'current_node' => 'assistant-'.$conversationId,
        'mapping' => [
            'user-'.$conversationId => [
                'id' => 'user-'.$conversationId,
                'parent' => null,
                'children' => ['assistant-'.$conversationId],
                'message' => [
                    'id' => 'user-'.$conversationId,
                    'author' => ['role' => 'user', 'name' => null, 'metadata' => []],
                    'content' => ['content_type' => 'text', 'parts' => ['Build the handoff.']],
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
                'parent' => 'user-'.$conversationId,
                'children' => [],
                'message' => [
                    'id' => 'assistant-'.$conversationId,
                    'author' => ['role' => 'assistant', 'name' => null, 'metadata' => []],
                    'content' => ['content_type' => 'text', 'parts' => ['Handoff built.']],
                    'metadata' => ['model_slug' => 'gpt-4'],
                    'status' => 'finished_successfully',
                    'recipient' => 'all',
                    'weight' => 1.0,
                    'create_time' => 1_697_528_122.017504,
                    'update_time' => null,
                    'end_turn' => true,
                    'channel' => null,
                ],
            ],
        ],
    ];
}
