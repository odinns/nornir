<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fidonet_source_id
 * @property string $area_code
 * @property-read FidonetSource $source
 */
class FidonetAreaObservation extends Model
{
    protected $table = 'fidonet_area_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<FidonetSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FidonetSource::class, 'fidonet_source_id');
    }
}
