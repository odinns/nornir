<?php

declare(strict_types=1);

use App\Models\ChatGptArchive;
use App\Models\ChatGptConversation;
use App\Models\ChatGptMessage;
use App\Models\IntakeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/chatgpt'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports chatgpt exports from the cli with useful default output', function (): void {
    $exportRoot = createConsoleChatGptExportDirectory([
        [buildConsoleChatGptConversation('console-conversation-1')],
        [buildConsoleChatGptConversation('console-conversation-2')],
    ]);

    artisanCommand($this, 'import:chatgpt', [
        'source' => $exportRoot,
    ])
        ->expectsOutputToContain('Recording intake for ChatGPT source')
        ->expectsOutputToContain('Found 2 conversation files to import')
        ->expectsOutputToContain('Importing ChatGPT conversations')
        ->expectsOutputToContain('[1/2] conversations-000.json')
        ->expectsOutputToContain('[2/2] conversations-001.json')
        ->expectsOutputToContain('Running totals:')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(IntakeRecord::query()->count())->toBe(1);
    expect(ChatGptConversation::query()->count())->toBe(2);
    expect(ChatGptMessage::query()->count())->toBe(4);
});

it('can import an archive path explicitly from the cli', function (): void {
    $archivePath = createConsoleChatGptArchiveFile([
        buildConsoleChatGptConversation(),
    ]);

    artisanCommand($this, 'import:chatgpt', [
        'source' => $archivePath,
        '--archive-label' => 'console-archive',
    ])->assertSuccessful();

    expect(IntakeRecord::query()->value('access_mode'))->toBe('archive');
    expect(ChatGptArchive::query()->value('archive_label'))->toBe('console-archive');
});

it('supports additional allowed roots for local path imports', function (): void {
    $primaryRoot = createConsoleChatGptExportDirectory([
        buildConsoleChatGptConversation(),
    ]);
    $secondaryRoot = storage_path('framework/testing/chatgpt-secondary-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($secondaryRoot);

    artisanCommand($this, 'import:chatgpt', [
        'source' => $primaryRoot,
        '--root' => [$secondaryRoot],
    ])->assertSuccessful();

    $scopeSnapshot = IntakeRecord::query()->firstOrFail()->scope_snapshot;
    $primaryRootPath = realpath($primaryRoot);
    $secondaryRootPath = realpath($secondaryRoot);

    if ($primaryRootPath === false || $secondaryRootPath === false) {
        throw new RuntimeException('Expected ChatGPT fixture roots to resolve.');
    }

    expect($scopeSnapshot['accepted_root_paths'] ?? [])->toContain($primaryRootPath);
    expect($scopeSnapshot['accepted_root_paths'] ?? [])->toContain($secondaryRootPath);
});

it('stays quiet when quiet mode is requested', function (): void {
    $exportRoot = createConsoleChatGptExportDirectory([
        buildConsoleChatGptConversation(),
    ]);

    artisanCommand($this, 'import:chatgpt', [
        'source' => $exportRoot,
        '--quiet' => true,
    ])->assertSuccessful();
});

/**
 * @param  list<array<string, mixed>>|list<list<array<string, mixed>>>  $conversations
 */
function createConsoleChatGptExportDirectory(array $conversations): string
{
    $path = storage_path('framework/testing/chatgpt-console-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($path);

    $files = isConsoleConversationList($conversations) ? [$conversations] : $conversations;

    foreach (array_values($files) as $index => $fileConversations) {
        file_put_contents(
            sprintf('%s/conversations-%03d.json', $path, $index),
            json_encode($fileConversations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    return $path;
}

/**
 * @param  list<array<string, mixed>>|list<list<array<string, mixed>>>  $payload
 */
function isConsoleConversationList(array $payload): bool
{
    if ($payload === []) {
        return true;
    }

    return is_array($payload[0] ?? null) && array_key_exists('mapping', $payload[0]);
}

/**
 * @param  list<array<string, mixed>>  $conversations
 */
function createConsoleChatGptArchiveFile(array $conversations): string
{
    $directory = storage_path('framework/testing/chatgpt-archive-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($directory);
    $path = $directory.'/conversations-000.json';

    file_put_contents(
        $path,
        json_encode($conversations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );

    return $path;
}

/**
 * @return array<string, mixed>
 */
function buildConsoleChatGptConversation(string $conversationId = 'console-conversation-1'): array
{
    return [
        'id' => $conversationId,
        'conversation_id' => $conversationId,
        'title' => 'Console importer conversation',
        'create_time' => 1_697_527_962.568149,
        'update_time' => 1_697_528_705.169732,
        'current_node' => 'assistant-console-1',
        'mapping' => [
            'user-console-1' => [
                'id' => 'user-console-1',
                'parent' => null,
                'children' => ['assistant-console-1'],
                'message' => [
                    'id' => 'user-console-1',
                    'author' => ['role' => 'user', 'name' => null, 'metadata' => []],
                    'content' => ['content_type' => 'text', 'parts' => ['Run the importer.']],
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
            'assistant-console-1' => [
                'id' => 'assistant-console-1',
                'parent' => 'user-console-1',
                'children' => [],
                'message' => [
                    'id' => 'assistant-console-1',
                    'author' => ['role' => 'assistant', 'name' => null, 'metadata' => []],
                    'content' => ['content_type' => 'text', 'parts' => ['Importer ran.']],
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
