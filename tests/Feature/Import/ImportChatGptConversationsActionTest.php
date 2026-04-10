<?php

declare(strict_types=1);

use App\Actions\Import\ImportChatGptConversationsAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\ProvenanceLink;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/chatgpt'));
    File::deleteDirectory(base_path('data/runs'));
});

it('imports a chatgpt export into canonical tables while preserving graph structure', function (): void {
    $exportRoot = createChatGptExportDirectory([
        buildChatGptConversation(),
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

    $result = app(ImportChatGptConversationsAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('chatgpt_archives')->count())->toBe(1);
    expect(DB::table('chatgpt_conversations')->count())->toBe(1);
    expect(DB::table('chatgpt_nodes')->count())->toBe(4);
    expect(DB::table('chatgpt_messages')->count())->toBe(4);
    expect(DB::table('chatgpt_message_parts')->count())->toBe(4);
    expect(DB::table('chatgpt_assets')->count())->toBe(1);

    $assistantMessage = DB::table('chatgpt_messages')
        ->where('message_id', 'assistant-1')
        ->first();

    expect($assistantMessage)->not->toBeNull();
    expect($assistantMessage->author_role)->toBe('assistant');

    $assistantParts = DB::table('chatgpt_message_parts')
        ->where('chatgpt_message_id', $assistantMessage->id)
        ->orderBy('part_index')
        ->pluck('text_part')
        ->all();

    expect($assistantParts)->toBe([
        'It is importer o clock.',
        'Still a terrible time for optimism.',
    ]);
});

it('reruns idempotently for the same export root', function (): void {
    $exportRoot = createChatGptExportDirectory([
        buildChatGptConversation(),
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

    $importer = app(ImportChatGptConversationsAction::class);

    $firstResult = $importer($intake->dispatchPayload);
    $secondResult = $importer($intake->dispatchPayload);

    expect($secondResult->run->is($firstResult->run))->toBeTrue();
    expect(DB::table('chatgpt_archives')->count())->toBe(1);
    expect(DB::table('chatgpt_conversations')->count())->toBe(1);
    expect(DB::table('chatgpt_nodes')->count())->toBe(4);
    expect(DB::table('chatgpt_messages')->count())->toBe(4);
    expect(DB::table('chatgpt_message_parts')->count())->toBe(4);
    expect(DB::table('chatgpt_assets')->count())->toBe(1);
});

it('fails clearly on malformed export data', function (): void {
    $exportRoot = createChatGptExportDirectory([
        [
            'id' => 'broken-conversation',
            'title' => 'Broken export',
        ],
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

    expect(fn () => app(ImportChatGptConversationsAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'Malformed ChatGPT conversation payload');

    $failedRun = Run::query()->latest('id')->first();

    expect($failedRun)->not->toBeNull();
    expect($failedRun->status)->toBe(Run::STATUS_FAILED);
    expect($failedRun->failure_summary)->toContain('Malformed ChatGPT conversation payload');
});

it('records importer artifacts and provenance links for the imported rows', function (): void {
    $exportRoot = createChatGptExportDirectory([
        buildChatGptConversation(),
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

    $result = app(ImportChatGptConversationsAction::class)($intake->dispatchPayload);

    $artifactLocators = $result->run->artifacts()->orderBy('id')->pluck('locator')->all();

    expect($artifactLocators)->toHaveCount(2);
    expect($artifactLocators[0])->toContain('data/imports/chatgpt/');
    expect($artifactLocators[1])->toContain('data/runs/import/');

    $links = ProvenanceLink::query()
        ->where('run_id', $result->run->id)
        ->orderBy('id')
        ->get();

    expect($links)->not->toBeEmpty();
    expect($links->pluck('output_target')->contains(fn (string $target): bool => str_contains($target, 'chatgpt_messages')))->toBeTrue();
    expect($links->pluck('evidence_ref')->contains('conversations-000.json#message:assistant-1'))->toBeTrue();
});

it('keeps structural nodes even when the export has null messages', function (): void {
    $conversation = buildChatGptConversation();
    $conversation['mapping']['null-root'] = [
        'id' => 'null-root',
        'parent' => null,
        'children' => ['root-node'],
        'message' => null,
    ];

    $exportRoot = createChatGptExportDirectory([$conversation]);

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

    $result = app(ImportChatGptConversationsAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('chatgpt_nodes')->where('node_id', 'null-root')->exists())->toBeTrue();
    expect(DB::table('chatgpt_messages')->where('message_id', 'null-root')->exists())->toBeFalse();
});

it('imports message parts longer than mysql text without truncation errors', function (): void {
    $conversation = buildChatGptConversation();
    $conversation['mapping']['assistant-1']['message']['content']['parts'][0] = str_repeat('A long answer. ', 6_000);

    $exportRoot = createChatGptExportDirectory([$conversation]);

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

    $result = app(ImportChatGptConversationsAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);

    $storedPart = DB::table('chatgpt_message_parts')
        ->where('part_type', 'text')
        ->orderByRaw('LENGTH(text_part) DESC')
        ->value('text_part');

    expect($storedPart)->toBe($conversation['mapping']['assistant-1']['message']['content']['parts'][0]);
});

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

function buildChatGptConversation(): array
{
    return [
        'id' => 'conversation-1',
        'conversation_id' => 'conversation-1',
        'title' => 'Importer test conversation',
        'create_time' => 1_697_527_962.568149,
        'update_time' => 1_697_528_705.169732,
        'current_node' => 'assistant-1',
        'mapping' => [
            'root-node' => [
                'id' => 'root-node',
                'parent' => null,
                'children' => ['user-1'],
                'message' => [
                    'id' => 'root-node',
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
            'user-1' => [
                'id' => 'user-1',
                'parent' => 'root-node',
                'children' => ['assistant-1'],
                'message' => [
                    'id' => 'user-1',
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
            'assistant-1' => [
                'id' => 'assistant-1',
                'parent' => 'user-1',
                'children' => ['user-2'],
                'message' => [
                    'id' => 'assistant-1',
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
            'user-2' => [
                'id' => 'user-2',
                'parent' => 'assistant-1',
                'children' => [],
                'message' => [
                    'id' => 'user-2',
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
