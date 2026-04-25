<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $canonical_key
 * @property string $workout_activity_type
 * @property string|null $source_name
 * @property string|null $source_version
 * @property string|null $duration
 * @property string|null $duration_unit
 * @property string|null $total_energy_burned
 * @property string|null $total_energy_burned_unit
 * @property string|null $total_distance
 * @property string|null $total_distance_unit
 * @property CarbonImmutable|null $creation_at
 * @property CarbonImmutable|null $start_at
 * @property CarbonImmutable|null $end_at
 * @property array<string, mixed>|null $raw_workout
 */
class AppleHealthWorkout extends Model
{
    protected $table = 'apple_health_workouts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'creation_at' => 'immutable_datetime',
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'raw_workout' => 'array',
        ];
    }
}
