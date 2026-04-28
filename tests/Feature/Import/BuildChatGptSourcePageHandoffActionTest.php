<?php

declare(strict_types=1);

use App\Actions\Import\BuildChatGptSourcePageHandoffAction;
use App\Actions\Import\ImportChatGptConversationsAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\ChatGptArchive;
use App\Models\ChatGptSourceSet;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a compile-facing handoff from canonical chatgpt rows', function (): void {
    $exportRoot = createHandoffChatGptExportDirectory([
        buildHandoffChatGptConversation('handoff-conversation-1'),
        buildHandoffChatGptConversation('handoff-conversation-2'),
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

    $handoff = app(BuildChatGptSourcePageHandoffAction::class)($importResult->run->id);
    /** @var array{source_locator:string, accepted_root_paths:list<string>, tables:list<string>, source_set_ids:list<int>, handoff_scope:array{source_set_ids:list<int>, selection_mode:string, biography_filter_required:bool}, row_counts:array{source_sets:int, conversations:int, messages:int}} $scope */
    $scope = $handoff->canonicalScope;
    $sourceSetIds = $scope['source_set_ids'];

    expect($handoff->sourceType)->toBe('chatgpt');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($sourceSetIds)->toHaveCount(1);
    expect($scope)->toMatchArray([
        'source_locator' => $exportRoot,
        'accepted_root_paths' => [$exportRoot],
        'tables' => [
            'chatgpt_archives',
            'chatgpt_source_sets',
            'chatgpt_conversations',
            'chatgpt_nodes',
            'chatgpt_messages',
            'chatgpt_message_parts',
            'chatgpt_assets',
            'chatgpt_conversation_observations',
            'chatgpt_message_observations',
        ],
        'source_set_ids' => $sourceSetIds,
        'handoff_scope' => [
            'source_set_ids' => $sourceSetIds,
            'selection_mode' => 'canonical-broad',
            'biography_filter_required' => true,
        ],
        'row_counts' => [
            'source_sets' => 1,
            'conversations' => 2,
            'messages' => 4,
        ],
    ]);
});

it('rejects runs that are not successful chatgpt imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'muninn',
        'operation' => 'timeline-pass',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['person' => 'odinn'],
        'idempotency_key' => 'timeline-pass:odinn',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildChatGptSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful ChatGPT import.');
});

it('builds the handoff from normalized rows without rescanning the raw export path', function (): void {
    $exportRoot = createHandoffChatGptExportDirectory([
        buildHandoffChatGptConversation('handoff-without-raw-source'),
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

    Illuminate\Support\Facades\File::deleteDirectory($exportRoot);

    $handoff = app(BuildChatGptSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->owningRunId)->toBe($importResult->run->id);
    /** @var array{row_counts:array{source_sets:int, conversations:int, messages:int}} $scope */
    $scope = $handoff->canonicalScope;
    expect($scope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'conversations' => 1,
        'messages' => 2,
    ]);
});

it('normalizes legacy relative source locators in the handoff payload', function (): void {
    $exportRoot = createHandoffChatGptExportDirectory([
        buildHandoffChatGptConversation('handoff-legacy-relative'),
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
    $relativeExportRoot = relativeToBasePathForHandoff($exportRoot);

    $importResult->run->update([
        'input_scope' => [
            'source_locator' => $relativeExportRoot,
            'scope_snapshot' => [
                'accepted_root_paths' => [$relativeExportRoot],
                'relative_glob' => 'conversations-*.json',
            ],
        ],
    ]);

    ChatGptArchive::query()->update([
        'source_locator' => $relativeExportRoot,
    ]);
    ChatGptSourceSet::query()->update([
        'source_locator' => $relativeExportRoot,
    ]);

    $handoff = app(BuildChatGptSourcePageHandoffAction::class)($importResult->run->id);

    /** @var array{source_locator:string, accepted_root_paths:list<string>} $scope */
    $scope = $handoff->canonicalScope;
    expect($scope['source_locator'])->toBe($exportRoot);
    expect($scope['accepted_root_paths'])->toBe([$exportRoot]);
});

/**
 * @param  list<array<string, mixed>>  $conversations
 */
function createHandoffChatGptExportDirectory(array $conversations): string
{
    $path = storage_path('framework/testing/chatgpt-handoff-'.bin2hex(random_bytes(4)));
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
function buildHandoffChatGptConversation(string $conversationId): array
{
    return [
        'id' => $conversationId,
        'conversation_id' => $conversationId,
        'title' => 'Handoff conversation',
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

function relativeToBasePathForHandoff(string $path): string
{
    return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
}
