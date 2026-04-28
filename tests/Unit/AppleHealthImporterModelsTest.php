<?php

declare(strict_types=1);

use App\Models\AppleHealthRecord;
use App\Models\AppleHealthRecordObservation;
use App\Models\AppleHealthSourceSet;
use App\Models\AppleHealthWorkout;
use App\Models\AppleHealthWorkoutObservation;
use Carbon\CarbonImmutable;

it('maps apple health importer tables through explicit eloquent model contracts', function (): void {
    $sourceSet = new AppleHealthSourceSet;
    $record = new AppleHealthRecord([
        'creation_at' => '2026-04-24 08:30:00',
        'start_at' => '2026-04-24 09:00:00',
        'end_at' => '2026-04-24 10:00:00',
        'raw_record' => ['type' => 'HKQuantityTypeIdentifierStepCount'],
    ]);
    $workout = new AppleHealthWorkout([
        'creation_at' => '2026-04-24 08:30:00',
        'start_at' => '2026-04-24 09:00:00',
        'end_at' => '2026-04-24 10:00:00',
        'raw_workout' => ['workoutActivityType' => 'HKWorkoutActivityTypeYoga'],
    ]);
    $recordObservation = new AppleHealthRecordObservation;
    $workoutObservation = new AppleHealthWorkoutObservation;

    expect($sourceSet->getTable())->toBe('apple_health_source_sets')
        ->and($sourceSet->recordObservations()->getForeignKeyName())->toBe('apple_health_source_set_id')
        ->and($sourceSet->workoutObservations()->getForeignKeyName())->toBe('apple_health_source_set_id');

    expect($record->getTable())->toBe('apple_health_records')
        ->and($record->creation_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($record->start_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($record->end_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($record->raw_record)->toBeArray()
        ->and($record->observations()->getForeignKeyName())->toBe('apple_health_record_id');

    expect($workout->getTable())->toBe('apple_health_workouts')
        ->and($workout->creation_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($workout->start_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($workout->end_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($workout->raw_workout)->toBeArray()
        ->and($workout->observations()->getForeignKeyName())->toBe('apple_health_workout_id');

    expect($recordObservation->getTable())->toBe('apple_health_record_observations')
        ->and($recordObservation->record()->getForeignKeyName())->toBe('apple_health_record_id')
        ->and($recordObservation->sourceSet()->getForeignKeyName())->toBe('apple_health_source_set_id');

    expect($workoutObservation->getTable())->toBe('apple_health_workout_observations')
        ->and($workoutObservation->workout()->getForeignKeyName())->toBe('apple_health_workout_id')
        ->and($workoutObservation->sourceSet()->getForeignKeyName())->toBe('apple_health_source_set_id');
});
