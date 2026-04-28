<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $apple_health_record_id
 * @property int $apple_health_source_set_id
 * @property-read AppleHealthRecord $record
 * @property-read AppleHealthSourceSet $sourceSet
 */
class AppleHealthRecordObservation extends Model
{
    protected $table = 'apple_health_record_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<AppleHealthRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(AppleHealthRecord::class, 'apple_health_record_id');
    }

    /**
     * @return BelongsTo<AppleHealthSourceSet, $this>
     */
    public function sourceSet(): BelongsTo
    {
        return $this->belongsTo(AppleHealthSourceSet::class, 'apple_health_source_set_id');
    }
}
