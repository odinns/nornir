<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $source_key
 * @property string $source_locator
 * @property string $access_mode
 * @property string $export_xml_path
 * @property-read Collection<int, AppleHealthRecordObservation> $recordObservations
 * @property-read Collection<int, AppleHealthWorkoutObservation> $workoutObservations
 */
class AppleHealthSourceSet extends Model
{
    protected $table = 'apple_health_source_sets';

    protected $guarded = [];

    /**
     * @return HasMany<AppleHealthRecordObservation, $this>
     */
    public function recordObservations(): HasMany
    {
        return $this->hasMany(AppleHealthRecordObservation::class, 'apple_health_source_set_id');
    }

    /**
     * @return HasMany<AppleHealthWorkoutObservation, $this>
     */
    public function workoutObservations(): HasMany
    {
        return $this->hasMany(AppleHealthWorkoutObservation::class, 'apple_health_source_set_id');
    }
}
