<?php

declare(strict_types=1);

use App\Actions\Import\BuildAppleMessagesSourcePageHandoffAction;
use App\Actions\Import\ImportAppleMessagesAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('builds a compile-facing handoff from canonical apple messages rows', function (): void {
    $fixture = createAppleMessagesFixtureDatabase('apple-messages-handoff', [
        'messages' => [
            [
                'guid' => 'msg-handoff-001',
                'text' => 'Handoff message one',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_400_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_400_010),
                'cache_has_attachments' => 0,
            ],
            [
                'guid' => 'msg-handoff-002',
                'text' => 'Handoff message two',
                'is_from_me' => 1,
                'handle_id' => null,
                'date' => appleTimestampForUnix(1_700_400_100),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_400_120),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $fixture['database_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['root_path']],
            'attachments_root' => $fixture['attachments_root'],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportAppleMessagesAction::class)($intake->dispatchPayload);

    $handoff = app(BuildAppleMessagesSourcePageHandoffAction::class)($importResult->run->id);
    /** @var array{source_locator:string, accepted_root_paths:list<string>, attachments_root:string, tables:list<string>, source_set_ids:list<int>, handoff_scope:array{source_set_ids:list<int>}, row_counts:array{source_sets:int, conversations:int, participants:int, messages:int, attachments:int}} $scope */
    $scope = $handoff->canonicalScope;
    $sourceSetIds = $scope['source_set_ids'];

    expect($handoff->sourceType)->toBe('apple-messages');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($sourceSetIds)->toHaveCount(1);
    expect($scope)->toMatchArray([
        'source_locator' => $fixture['database_path'],
        'accepted_root_paths' => [$fixture['root_path']],
        'attachments_root' => $fixture['attachments_root'],
        'tables' => [
            'apple_messages_source_sets',
            'apple_messages_conversations',
            'apple_messages_participants',
            'apple_messages_messages',
            'apple_messages_attachments',
            'apple_messages_message_observations',
        ],
        'source_set_ids' => $sourceSetIds,
        'handoff_scope' => [
            'source_set_ids' => $sourceSetIds,
        ],
        'row_counts' => [
            'source_sets' => 1,
            'conversations' => 1,
            'participants' => 1,
            'messages' => 2,
            'attachments' => 0,
        ],
    ]);
});

it('rejects runs that are not successful apple messages imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'muninn',
        'operation' => 'timeline-pass',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['person' => 'odinn'],
        'idempotency_key' => 'timeline-pass:odinn',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildAppleMessagesSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful Apple Messages import.');
});

it('builds the apple messages handoff from canonical rows without rescanning the raw source path', function (): void {
    $fixture = createAppleMessagesFixtureDatabase('apple-messages-handoff-no-raw', [
        'messages' => [
            [
                'guid' => 'msg-handoff-rawless-001',
                'text' => 'Raw path can vanish now',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_500_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_500_010),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $fixture['database_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['root_path']],
            'attachments_root' => $fixture['attachments_root'],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportAppleMessagesAction::class)($intake->dispatchPayload);

    File::deleteDirectory($fixture['root_path']);

    $handoff = app(BuildAppleMessagesSourcePageHandoffAction::class)($importResult->run->id);

    /** @var array{row_counts:array{source_sets:int, conversations:int, participants:int, messages:int, attachments:int}} $scope */
    $scope = $handoff->canonicalScope;
    expect($scope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'conversations' => 1,
        'participants' => 1,
        'messages' => 1,
        'attachments' => 0,
    ]);
});
