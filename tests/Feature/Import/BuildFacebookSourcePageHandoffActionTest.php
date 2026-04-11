<?php

declare(strict_types=1);

use App\Actions\Import\BuildFacebookSourcePageHandoffAction;
use App\Actions\Import\ImportFacebookArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('builds a compile-facing handoff from canonical facebook rows', function (): void {
    $fixture = createFacebookFixtureArchive('facebook-handoff', [
        'friends' => [
            ['name' => 'Alice Friend', 'timestamp' => 1_700_400_000],
        ],
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'handoffthread_123',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [
                [
                    'sender_name' => 'Alice Friend',
                    'timestamp_ms' => 1_700_400_000_000,
                    'content' => 'Handoff message one',
                ],
                [
                    'sender_name' => 'Odinn Test',
                    'timestamp_ms' => 1_700_400_060_000,
                    'content' => 'Handoff message two',
                ],
            ],
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'facebook',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportFacebookArchiveAction::class)($intake->dispatchPayload);
    $handoff = app(BuildFacebookSourcePageHandoffAction::class)($importResult->run->id);
    $archiveIds = $handoff->canonicalScope['source_set_ids'];

    expect($handoff->sourceType)->toBe('facebook');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($archiveIds)->toHaveCount(1);
    expect($handoff->canonicalScope)->toMatchArray([
        'source_locator' => $fixture['archive_path'],
        'accepted_root_paths' => [$fixture['archive_path']],
        'source_set_ids' => $archiveIds,
        'handoff_scope' => [
            'source_set_ids' => $archiveIds,
        ],
        'row_counts' => [
            'source_sets' => 1,
            'conversations' => 1,
            'messages' => 2,
            'people' => 2,
            'posts' => 0,
            'comments' => 0,
            'reactions' => 0,
        ],
    ]);
});

it('rejects runs that are not successful facebook imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'muninn',
        'operation' => 'timeline-pass',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['person' => 'odinn'],
        'idempotency_key' => 'timeline-pass:odinn',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildFacebookSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful Facebook import.');
});

it('builds the facebook handoff from canonical rows without rescanning the raw source path', function (): void {
    $fixture = createFacebookFixtureArchive('facebook-handoff-no-raw', [
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'rawlessthread_123',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [[
                'sender_name' => 'Alice Friend',
                'timestamp_ms' => 1_700_500_000_000,
                'content' => 'Raw path can vanish now',
            ]],
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'facebook',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportFacebookArchiveAction::class)($intake->dispatchPayload);

    File::deleteDirectory($fixture['root_path']);

    $handoff = app(BuildFacebookSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->canonicalScope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'conversations' => 1,
        'messages' => 1,
        'people' => 2,
        'posts' => 0,
        'comments' => 0,
        'reactions' => 0,
    ]);
});
