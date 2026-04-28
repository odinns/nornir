<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $apple_health_workout_id
 * @property int $apple_health_source_set_id
 * @property-read AppleHealthWorkout $workout
 * @property-read AppleHealthSourceSet $sourceSet
 */
class AppleHealthWorkoutObservation extends Model
{
    protected $table = 'apple_health_workout_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<AppleHealthWorkout, $this>
     */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(AppleHealthWorkout::class, 'apple_health_workout_id');
    }

    /**
     * @return BelongsTo<AppleHealthSourceSet, $this>
     */
    public function sourceSet(): BelongsTo
    {
        return $this->belongsTo(AppleHealthSourceSet::class, 'apple_health_source_set_id');
    }
}
