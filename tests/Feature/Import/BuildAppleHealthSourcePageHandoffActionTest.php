<?php

declare(strict_types=1);

use App\Actions\Import\BuildAppleHealthSourcePageHandoffAction;
use App\Actions\Import\ImportAppleHealthAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('builds a compile-facing handoff from canonical apple health rows', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-handoff', [
        'records' => [[
            'type' => 'HKCategoryTypeIdentifierSleepAnalysis',
            'source_name' => 'Sleep Cycle',
            'source_version' => '4906',
            'value' => 'HKCategoryValueSleepAnalysisAsleepUnspecified',
            'creation_date' => '2024-04-07 07:00:00 +0200',
            'start_date' => '2024-04-06 23:00:00 +0200',
            'end_date' => '2024-04-07 07:00:00 +0200',
        ]],
        'workouts' => [[
            'workout_activity_type' => 'HKWorkoutActivityTypeYoga',
            'source_name' => 'Yoga Studio',
            'source_version' => '1.0',
            'creation_date' => '2024-04-07 18:00:00 +0200',
            'start_date' => '2024-04-07 17:00:00 +0200',
            'end_date' => '2024-04-07 18:00:00 +0200',
            'duration' => 60,
            'duration_unit' => 'min',
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-health',
        accessMode: 'local-path',
        sourceLocator: $fixture['root_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['root_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportAppleHealthAction::class)($intake->dispatchPayload);

    $handoff = app(BuildAppleHealthSourcePageHandoffAction::class)($importResult->run->id);
    /** @var array{source_locator:string, accepted_root_paths:list<string>, tables:list<string>, source_set_ids:list<int>, handoff_scope:array{source_set_ids:list<int>}, row_counts:array{source_sets:int, records:int, workouts:int}} $scope */
    $scope = $handoff->canonicalScope;
    $sourceSetIds = $scope['source_set_ids'];

    expect($handoff->sourceType)->toBe('apple-health');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($sourceSetIds)->toHaveCount(1);
    expect($scope)->toMatchArray([
        'source_locator' => $fixture['root_path'],
        'accepted_root_paths' => [$fixture['root_path']],
        'tables' => [
            'apple_health_source_sets',
            'apple_health_records',
            'apple_health_workouts',
            'apple_health_record_observations',
            'apple_health_workout_observations',
        ],
        'source_set_ids' => $sourceSetIds,
        'handoff_scope' => [
            'source_set_ids' => $sourceSetIds,
        ],
        'row_counts' => [
            'source_sets' => 1,
            'records' => 1,
            'workouts' => 1,
        ],
    ]);
});

it('rejects runs that are not successful apple health imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'muninn',
        'operation' => 'timeline-pass',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['person' => 'odinn'],
        'idempotency_key' => 'timeline-pass:odinn',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildAppleHealthSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful Apple Health import.');
});

it('builds the apple health handoff from canonical rows without rescanning the raw source path', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-handoff-no-raw', [
        'records' => [[
            'type' => 'HKQuantityTypeIdentifierStepCount',
            'source_name' => 'Odinns iPhone Thirteen Pro',
            'source_version' => '17.4',
            'unit' => 'count',
            'value' => '99',
            'creation_date' => '2024-04-08 10:05:00 +0200',
            'start_date' => '2024-04-08 10:00:00 +0200',
            'end_date' => '2024-04-08 10:05:00 +0200',
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-health',
        accessMode: 'local-path',
        sourceLocator: $fixture['root_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['root_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportAppleHealthAction::class)($intake->dispatchPayload);

    File::deleteDirectory($fixture['root_path']);

    $handoff = app(BuildAppleHealthSourcePageHandoffAction::class)($importResult->run->id);

    /** @var array{row_counts:array{source_sets:int, records:int, workouts:int}} $scope */
    $scope = $handoff->canonicalScope;
    expect($scope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'records' => 1,
        'workouts' => 0,
    ]);
});
