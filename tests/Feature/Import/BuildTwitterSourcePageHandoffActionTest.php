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

it('scopes twitter handoff rows through source-set observations for thinner later exports', function (): void {
    $fullArchive = createTwitterFixtureArchive('twitter-handoff-full');
    $truncatedArchive = createTwitterFixtureArchive('twitter-handoff-truncated', [
        'include_community_tweets' => false,
        'include_note_tweets' => false,
        'include_screen_name_changes' => false,
        'tweets' => [[
            'id_str' => '111',
            'created_at' => 'Tue Feb 17 06:30:43 +0000 2026',
            'full_text' => 'Hello from the importer',
            'source' => 'Twitter Web App',
            'lang' => 'en',
            'conversation_id_str' => '111',
        ]],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportTwitterArchiveAction::class);

    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fullArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fullArchive['archive_path']],
        ],
        importerOptions: [],
    ));
    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $truncatedArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedArchive['archive_path']],
        ],
        importerOptions: [],
    ));

    $importer($fullIntake->dispatchPayload);
    $truncatedResult = $importer($truncatedIntake->dispatchPayload);

    $handoff = app(BuildTwitterSourcePageHandoffAction::class)($truncatedResult->run->id);

    /** @var array{source_set_ids:list<int>, row_counts:array{source_sets:int, accounts:int, profile_snapshots:int, tweets:int, note_tweets:int, media_refs:int}} $scope */
    $scope = $handoff->canonicalScope;

    expect($scope['source_set_ids'])->toHaveCount(1);
    expect($scope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'accounts' => 1,
        'profile_snapshots' => 1,
        'tweets' => 1,
        'note_tweets' => 0,
        'media_refs' => 2,
    ]);
});

it('includes shared note tweets only for source sets where they were observed', function (): void {
    $fullArchive = createTwitterFixtureArchive('twitter-handoff-note-full');
    $noteOnlyArchive = createTwitterFixtureArchive('twitter-handoff-note-shared', [
        'include_community_tweets' => false,
        'tweets' => [[
            'id_str' => '111',
            'created_at' => 'Tue Feb 17 06:30:43 +0000 2026',
            'full_text' => 'Hello from the importer',
            'source' => 'Twitter Web App',
            'lang' => 'en',
            'conversation_id_str' => '111',
        ]],
    ]);
    $withoutNoteArchive = createTwitterFixtureArchive('twitter-handoff-note-missing', [
        'include_community_tweets' => false,
        'include_note_tweets' => false,
        'tweets' => [[
            'id_str' => '111',
            'created_at' => 'Tue Feb 17 06:30:43 +0000 2026',
            'full_text' => 'Hello from the importer',
            'source' => 'Twitter Web App',
            'lang' => 'en',
            'conversation_id_str' => '111',
        ]],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportTwitterArchiveAction::class);

    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fullArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fullArchive['archive_path']],
        ],
        importerOptions: [],
    ));
    $noteOnlyIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $noteOnlyArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$noteOnlyArchive['archive_path']],
        ],
        importerOptions: [],
    ));
    $withoutNoteIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $withoutNoteArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$withoutNoteArchive['archive_path']],
        ],
        importerOptions: [],
    ));

    $importer($fullIntake->dispatchPayload);
    $noteOnlyResult = $importer($noteOnlyIntake->dispatchPayload);
    $withoutNoteResult = $importer($withoutNoteIntake->dispatchPayload);

    $noteOnlyHandoff = app(BuildTwitterSourcePageHandoffAction::class)($noteOnlyResult->run->id);
    $withoutNoteHandoff = app(BuildTwitterSourcePageHandoffAction::class)($withoutNoteResult->run->id);

    /** @var array{row_counts:array{note_tweets:int}} $noteOnlyScope */
    $noteOnlyScope = $noteOnlyHandoff->canonicalScope;
    /** @var array{row_counts:array{note_tweets:int}} $withoutNoteScope */
    $withoutNoteScope = $withoutNoteHandoff->canonicalScope;

    expect($noteOnlyScope['row_counts']['note_tweets'])->toBe(1)
        ->and($withoutNoteScope['row_counts']['note_tweets'])->toBe(0);
});
