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
        ->where('message_id', 'assistant-conversation-1')
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

it('stores normalized utc datetimes alongside raw source times', function (): void {
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

    $conversation = DB::table('chatgpt_conversations')
        ->where('conversation_id', 'conversation-1')
        ->first();

    $assistantMessage = DB::table('chatgpt_messages')
        ->where('message_id', 'assistant-conversation-1')
        ->first();

    expect($conversation)->not->toBeNull();
    expect($conversation->source_create_time)->toBeFloat();
    expect($conversation->source_update_time)->toBeFloat();
    expect($conversation->conversation_created_at)->toBe('2023-10-17 07:32:42');
    expect($conversation->conversation_updated_at)->toBe('2023-10-17 07:45:05');

    expect($assistantMessage)->not->toBeNull();
    expect($assistantMessage->source_create_time)->toBeFloat();
    expect($assistantMessage->source_update_time)->toBeNull();
    expect($assistantMessage->message_created_at)->toBe('2023-10-17 07:35:22');
    expect($assistantMessage->message_updated_at)->toBeNull();
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
    expect(DB::table('chatgpt_source_sets')->count())->toBe(1);
    expect(DB::table('chatgpt_conversations')->count())->toBe(1);
    expect(DB::table('chatgpt_nodes')->count())->toBe(4);
    expect(DB::table('chatgpt_messages')->count())->toBe(4);
    expect(DB::table('chatgpt_message_parts')->count())->toBe(4);
    expect(DB::table('chatgpt_assets')->count())->toBe(1);
    expect(DB::table('chatgpt_message_observations')->count())->toBe(4);
    expect($secondResult->summary['inserted_messages'])->toBe(0);
    expect($secondResult->summary['reobserved_messages'])->toBe(4);
});

it('keeps older canonical conversations when a newer export omits them', function (): void {
    $fullExportRoot = createChatGptExportDirectory([
        buildChatGptConversation('conversation-history-1'),
        buildChatGptConversation('conversation-history-2'),
    ]);
    $truncatedExportRoot = createChatGptExportDirectory([
        buildChatGptConversation('conversation-history-2'),
        buildChatGptConversation('conversation-history-3'),
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportChatGptConversationsAction::class);

    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $fullExportRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$fullExportRoot],
            'relative_glob' => 'conversations-*.json',
        ],
        importerOptions: [],
    ));
    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $truncatedExportRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedExportRoot],
            'relative_glob' => 'conversations-*.json',
        ],
        importerOptions: [],
    ));

    $importer($fullIntake->dispatchPayload);
    $result = $importer($truncatedIntake->dispatchPayload);

    expect(DB::table('chatgpt_source_sets')->count())->toBe(2);
    expect(DB::table('chatgpt_conversations')->count())->toBe(3);
    expect(DB::table('chatgpt_messages')->count())->toBe(12);
    expect(DB::table('chatgpt_message_observations')->count())->toBe(16);
    expect(DB::table('chatgpt_conversations')->pluck('conversation_id')->all())->toEqualCanonicalizing([
        'conversation-history-1',
        'conversation-history-2',
        'conversation-history-3',
    ]);
    expect($result->summary['inserted_messages'])->toBe(4);
    expect($result->summary['reobserved_messages'])->toBe(4);
});

it('backfills missing canonical conversations when an older fuller export arrives later', function (): void {
    $truncatedExportRoot = createChatGptExportDirectory([
        buildChatGptConversation('conversation-backfill-2'),
    ]);
    $fullExportRoot = createChatGptExportDirectory([
        buildChatGptConversation('conversation-backfill-1'),
        buildChatGptConversation('conversation-backfill-2'),
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportChatGptConversationsAction::class);

    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $truncatedExportRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedExportRoot],
            'relative_glob' => 'conversations-*.json',
        ],
        importerOptions: [],
    ));
    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $fullExportRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$fullExportRoot],
            'relative_glob' => 'conversations-*.json',
        ],
        importerOptions: [],
    ));

    $importer($truncatedIntake->dispatchPayload);
    $result = $importer($fullIntake->dispatchPayload);

    expect(DB::table('chatgpt_source_sets')->count())->toBe(2);
    expect(DB::table('chatgpt_conversations')->count())->toBe(2);
    expect(DB::table('chatgpt_messages')->count())->toBe(8);
    expect(DB::table('chatgpt_message_observations')->count())->toBe(12);
    expect(DB::table('chatgpt_conversations')->pluck('conversation_id')->all())->toEqualCanonicalizing([
        'conversation-backfill-1',
        'conversation-backfill-2',
    ]);
    expect($result->summary['inserted_messages'])->toBe(4);
    expect($result->summary['reobserved_messages'])->toBe(4);
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
    expect($links->pluck('evidence_ref')->contains('conversations-000.json#message:assistant-conversation-1'))->toBeTrue();
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
    $conversation['mapping']['assistant-conversation-1']['message']['content']['parts'][0] = str_repeat('A long answer. ', 6_000);

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

    expect($storedPart)->toBe($conversation['mapping']['assistant-conversation-1']['message']['content']['parts'][0]);
});
