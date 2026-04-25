<?php

declare(strict_types=1);

use App\Actions\Import\BuildTwitterSourcePageHandoffAction;
use App\Actions\Import\ImportTwitterArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('builds a compile-facing handoff from canonical twitter rows', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-handoff');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportTwitterArchiveAction::class)($intake->dispatchPayload);
    $handoff = app(BuildTwitterSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->sourceType)->toBe('twitter');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($handoff->canonicalScope)->toMatchArray([
        'source_locator' => $fixture['archive_path'],
        'accepted_root_paths' => [$fixture['archive_path']],
        'row_counts' => [
            'source_sets' => 1,
            'accounts' => 1,
            'profile_snapshots' => 1,
            'tweets' => 2,
            'note_tweets' => 1,
            'media_refs' => 4,
        ],
    ]);
});

it('rejects runs that are not successful twitter imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'muninn',
        'operation' => 'timeline-pass',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['person' => 'odinn'],
        'idempotency_key' => 'timeline-pass:odinn',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildTwitterSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful Twitter import.');
});

it('builds the twitter handoff from canonical rows without rescanning the raw source path', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-handoff-no-raw');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportTwitterArchiveAction::class)($intake->dispatchPayload);

    File::deleteDirectory($fixture['root_path']);

    $handoff = app(BuildTwitterSourcePageHandoffAction::class)($importResult->run->id);

    /** @var array{row_counts:array{source_sets:int, accounts:int, profile_snapshots:int, tweets:int, note_tweets:int, media_refs:int}} $scope */
    $scope = $handoff->canonicalScope;
    expect($scope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'accounts' => 1,
        'profile_snapshots' => 1,
        'tweets' => 2,
        'note_tweets' => 1,
        'media_refs' => 4,
    ]);
});
