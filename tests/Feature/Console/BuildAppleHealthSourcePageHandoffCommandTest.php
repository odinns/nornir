<?php

declare(strict_types=1);

use App\Actions\Import\ImportAppleHealthAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds an apple health source-page handoff from the latest successful import run', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-handoff-cli', [
        'records' => [[
            'type' => 'HKQuantityTypeIdentifierStepCount',
            'source_name' => 'Odinns iPhone Thirteen Pro',
            'source_version' => '17.4',
            'unit' => 'count',
            'value' => '111',
            'creation_date' => '2024-04-11 10:05:00 +0200',
            'start_date' => '2024-04-11 10:00:00 +0200',
            'end_date' => '2024-04-11 10:05:00 +0200',
        ]],
        'workouts' => [[
            'workout_activity_type' => 'HKWorkoutActivityTypeYoga',
            'source_name' => 'Yoga Studio',
            'source_version' => '1.0',
            'creation_date' => '2024-04-11 18:00:00 +0200',
            'start_date' => '2024-04-11 17:00:00 +0200',
            'end_date' => '2024-04-11 18:00:00 +0200',
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

    artisanCommand($this, 'handoff:apple-health-source-pages')
        ->expectsOutputToContain('Building Apple Health source-page handoff')
        ->expectsOutputToContain("Using run id: {$importResult->run->id}")
        ->expectsOutputToContain('Source locator: '.$fixture['root_path'])
        ->expectsOutputToContain('Source set count: 1')
        ->expectsOutputToContain('Record count: 1')
        ->expectsOutputToContain('Workout count: 1')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});
