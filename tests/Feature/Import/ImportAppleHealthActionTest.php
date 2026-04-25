<?php

declare(strict_types=1);

use App\Actions\Import\ImportAppleHealthAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\ProvenanceLink;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/apple-health'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports apple health records into canonical tables', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-import-primary', [
        'me' => [
            'HKCharacteristicTypeIdentifierDateOfBirth' => '1969-02-17',
            'HKCharacteristicTypeIdentifierBiologicalSex' => 'HKBiologicalSexMale',
        ],
        'records' => [[
            'type' => 'HKCategoryTypeIdentifierSleepAnalysis',
            'source_name' => 'Sleep Cycle',
            'source_version' => '4906',
            'value' => 'HKCategoryValueSleepAnalysisAsleepUnspecified',
            'creation_date' => '2024-04-01 07:00:00 +0200',
            'start_date' => '2024-03-31 23:00:00 +0200',
            'end_date' => '2024-04-01 07:00:00 +0200',
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

    $result = app(ImportAppleHealthAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('apple_health_source_sets')->count())->toBe(1);
    expect(DB::table('apple_health_records')->count())->toBe(1);
    expect(DB::table('apple_health_workouts')->count())->toBe(0);

    $record = DB::table('apple_health_records')->first();

    expect($record)->not->toBeNull();

    if ($record === null) {
        return;
    }

    expect($record->record_type)->toBe('HKCategoryTypeIdentifierSleepAnalysis');
    expect($record->source_name)->toBe('Sleep Cycle');
    expect($record->value)->toBe('HKCategoryValueSleepAnalysisAsleepUnspecified');
    expect($record->unit)->toBeNull();
    expect($record->start_at)->toBe('2024-03-31 21:00:00');
    expect($record->end_at)->toBe('2024-04-01 05:00:00');
    expect(DB::table('apple_health_records')->where('record_type', 'HKCharacteristicTypeIdentifierDateOfBirth')->doesntExist())
        ->toBeTrue();
});

it('imports workouts into canonical tables', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-import-workouts', [
        'workouts' => [[
            'workout_activity_type' => 'HKWorkoutActivityTypeYoga',
            'source_name' => 'Yoga Studio',
            'source_version' => '1.0',
            'creation_date' => '2024-04-02 10:00:00 +0200',
            'start_date' => '2024-04-02 09:00:00 +0200',
            'end_date' => '2024-04-02 10:00:00 +0200',
            'duration' => 60,
            'duration_unit' => 'min',
            'total_energy_burned' => '220',
            'total_energy_burned_unit' => 'kcal',
            'total_distance' => '1.5',
            'total_distance_unit' => 'km',
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

    app(ImportAppleHealthAction::class)($intake->dispatchPayload);

    expect(DB::table('apple_health_workouts')->count())->toBe(1);
    expect(DB::table('apple_health_records')->count())->toBe(0);
    expect(DB::table('apple_health_workouts')->value('workout_activity_type'))->toBe('HKWorkoutActivityTypeYoga');
    expect(DB::table('apple_health_workouts')->value('total_energy_burned'))->toBe('220');
});

it('reruns idempotently for the same apple health export', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-import-repeat', [
        'records' => [[
            'type' => 'HKQuantityTypeIdentifierStepCount',
            'source_name' => 'Sample iPhone Thirteen Pro',
            'source_version' => '17.4',
            'unit' => 'count',
            'value' => '1234',
            'creation_date' => '2024-04-03 12:05:00 +0200',
            'start_date' => '2024-04-03 12:00:00 +0200',
            'end_date' => '2024-04-03 12:05:00 +0200',
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

    $importer = app(ImportAppleHealthAction::class);

    $firstResult = $importer($intake->dispatchPayload);
    $secondResult = $importer($intake->dispatchPayload);

    expect($secondResult->run->is($firstResult->run))->toBeTrue();
    expect(DB::table('apple_health_source_sets')->count())->toBe(1);
    expect(DB::table('apple_health_records')->count())->toBe(1);
    expect(DB::table('apple_health_record_observations')->count())->toBe(1);
    expect($secondResult->summary['inserted_records'])->toBe(0);
    expect($secondResult->summary['reobserved_records'])->toBe(1);
});

it('keeps older canonical rows when a later export is incomplete', function (): void {
    $fullFixture = createAppleHealthFixtureExport('apple-health-import-full', [
        'records' => [
            [
                'type' => 'HKQuantityTypeIdentifierStepCount',
                'source_name' => 'Sample iPhone Thirteen Pro',
                'source_version' => '17.4',
                'unit' => 'count',
                'value' => '100',
                'creation_date' => '2024-04-04 08:05:00 +0200',
                'start_date' => '2024-04-04 08:00:00 +0200',
                'end_date' => '2024-04-04 08:05:00 +0200',
            ],
            [
                'type' => 'HKQuantityTypeIdentifierStepCount',
                'source_name' => 'Sample iPhone Thirteen Pro',
                'source_version' => '17.4',
                'unit' => 'count',
                'value' => '200',
                'creation_date' => '2024-04-04 09:05:00 +0200',
                'start_date' => '2024-04-04 09:00:00 +0200',
                'end_date' => '2024-04-04 09:05:00 +0200',
            ],
        ],
    ]);
    $truncatedFixture = createAppleHealthFixtureExport('apple-health-import-truncated', [
        'records' => [[
            'type' => 'HKQuantityTypeIdentifierStepCount',
            'source_name' => 'Sample iPhone Thirteen Pro',
            'source_version' => '17.4',
            'unit' => 'count',
            'value' => '200',
            'creation_date' => '2024-04-04 09:05:00 +0200',
            'start_date' => '2024-04-04 09:00:00 +0200',
            'end_date' => '2024-04-04 09:05:00 +0200',
        ]],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportAppleHealthAction::class);

    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'apple-health',
        accessMode: 'local-path',
        sourceLocator: $fullFixture['root_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fullFixture['root_path']],
        ],
        importerOptions: [],
    ));
    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'apple-health',
        accessMode: 'local-path',
        sourceLocator: $truncatedFixture['root_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedFixture['root_path']],
        ],
        importerOptions: [],
    ));

    $importer($fullIntake->dispatchPayload);
    $importer($truncatedIntake->dispatchPayload);

    expect(DB::table('apple_health_source_sets')->count())->toBe(2);
    expect(DB::table('apple_health_records')->count())->toBe(2);
});

it('accepts a direct path to eksport.xml as an archive source', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-import-archive', [
        'records' => [[
            'type' => 'HKQuantityTypeIdentifierHeartRate',
            'source_name' => 'Sleep Cycle',
            'source_version' => '4906',
            'unit' => 'count/min',
            'value' => '61',
            'creation_date' => '2024-04-05 07:00:00 +0200',
            'start_date' => '2024-04-05 06:59:58 +0200',
            'end_date' => '2024-04-05 06:59:58 +0200',
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-health',
        accessMode: 'archive',
        sourceLocator: $fixture['export_xml_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['root_path']],
        ],
        importerOptions: [],
    ));

    app(ImportAppleHealthAction::class)($intake->dispatchPayload);

    expect(DB::table('apple_health_records')->count())->toBe(1);
});

it('fails clearly when eksport.xml is missing from a local-path source', function (): void {
    $root = storage_path('framework/testing/apple-health-missing-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($root);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-health',
        accessMode: 'local-path',
        sourceLocator: $root,
        scopeSnapshot: [
            'accepted_root_paths' => [$root],
        ],
        importerOptions: [],
    ));

    expect(fn () => app(ImportAppleHealthAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'Malformed Apple Health source payload: eksport.xml was not found inside the requested directory.');
});

it('fails clearly when eksport.xml is malformed', function (): void {
    $root = storage_path('framework/testing/apple-health-malformed-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($root);
    File::put($root.'/eksport.xml', '<HealthData><Record');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-health',
        accessMode: 'local-path',
        sourceLocator: $root,
        scopeSnapshot: [
            'accepted_root_paths' => [$root],
        ],
        importerOptions: [],
    ));

    expect(fn () => app(ImportAppleHealthAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'Malformed Apple Health source payload: eksport.xml could not be parsed.');

    $failedRun = Run::query()->latest('id')->first();

    expect($failedRun)->not->toBeNull();

    if ($failedRun === null) {
        return;
    }

    expect($failedRun->status)->toBe(Run::STATUS_FAILED);
});

it('records importer artifacts and provenance links for imported apple health rows', function (): void {
    $fixture = createAppleHealthFixtureExport('apple-health-import-artifacts', [
        'records' => [[
            'type' => 'HKQuantityTypeIdentifierStepCount',
            'source_name' => 'Sample iPhone Thirteen Pro',
            'source_version' => '17.4',
            'unit' => 'count',
            'value' => '345',
            'creation_date' => '2024-04-06 12:05:00 +0200',
            'start_date' => '2024-04-06 12:00:00 +0200',
            'end_date' => '2024-04-06 12:05:00 +0200',
        ]],
        'workouts' => [[
            'workout_activity_type' => 'HKWorkoutActivityTypeYoga',
            'source_name' => 'Yoga Studio',
            'source_version' => '1.0',
            'creation_date' => '2024-04-06 18:00:00 +0200',
            'start_date' => '2024-04-06 17:00:00 +0200',
            'end_date' => '2024-04-06 18:00:00 +0200',
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

    $result = app(ImportAppleHealthAction::class)($intake->dispatchPayload);

    $artifactLocators = $result->run->artifacts()
        ->orderBy('id')
        ->pluck('locator')
        ->map(static fn (mixed $locator): string => (string) $locator)
        ->all();

    expect($artifactLocators)->toHaveCount(2);
    $importArtifact = $artifactLocators[0] ?? throw new RuntimeException('Expected Apple Health import artifact locator.');
    $runArtifact = $artifactLocators[1] ?? throw new RuntimeException('Expected Apple Health run artifact locator.');
    expect($importArtifact)->toContain('data/imports/apple-health/');
    expect($runArtifact)->toContain('data/runs/import/');

    $links = ProvenanceLink::query()
        ->where('run_id', $result->run->id)
        ->orderBy('id')
        ->get();

    expect($links)->not->toBeEmpty();
    expect($links->pluck('output_target')->contains(fn (string $target): bool => str_contains($target, 'apple_health_records:')))->toBeTrue();
    expect($links->pluck('output_target')->contains(fn (string $target): bool => str_contains($target, 'apple_health_workouts:')))->toBeTrue();
});
