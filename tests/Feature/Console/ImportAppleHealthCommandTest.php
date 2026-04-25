<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/apple-health'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports apple health exports from the cli with useful default output', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-console', [
        'records' => [[
            'type' => 'HKCategoryTypeIdentifierSleepAnalysis',
            'source_name' => 'Sleep Cycle',
            'source_version' => '4906',
            'value' => 'HKCategoryValueSleepAnalysisAsleepUnspecified',
            'creation_date' => '2024-04-09 07:00:00 +0200',
            'start_date' => '2024-04-08 23:00:00 +0200',
            'end_date' => '2024-04-09 07:00:00 +0200',
        ]],
        'workouts' => [[
            'workout_activity_type' => 'HKWorkoutActivityTypeYoga',
            'source_name' => 'Yoga Studio',
            'source_version' => '1.0',
            'creation_date' => '2024-04-09 18:00:00 +0200',
            'start_date' => '2024-04-09 17:00:00 +0200',
            'end_date' => '2024-04-09 18:00:00 +0200',
            'duration' => 60,
            'duration_unit' => 'min',
        ]],
    ]);

    artisanCommand($this, 'import:apple-health', [
        'source' => $fixture['root_path'],
    ])
        ->expectsOutputToContain('Recording intake for Apple Health source')
        ->expectsOutputToContain('Importing Apple Health records')
        ->expectsOutputToContain('Import complete')
        ->expectsOutputToContain('Source file: eksport.xml')
        ->expectsOutputToContain('Imported records: 1')
        ->expectsOutputToContain('Imported workouts: 1')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(1);
    expect(DB::table('apple_health_records')->count())->toBe(1);
    expect(DB::table('apple_health_workouts')->count())->toBe(1);
});

it('stays quiet when quiet mode is requested', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-console-quiet', [
        'records' => [[
            'type' => 'HKQuantityTypeIdentifierStepCount',
            'source_name' => 'Odinns iPhone Thirteen Pro',
            'source_version' => '17.4',
            'unit' => 'count',
            'value' => '88',
            'creation_date' => '2024-04-10 08:05:00 +0200',
            'start_date' => '2024-04-10 08:00:00 +0200',
            'end_date' => '2024-04-10 08:05:00 +0200',
        ]],
    ]);

    artisanCommand($this, 'import:apple-health', [
        'source' => $fixture['root_path'],
        '--quiet' => true,
    ])->assertSuccessful();
});
