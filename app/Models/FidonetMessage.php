<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $canonical_message_id
 * @property string $area_code
 * @property string|null $subject
 * @property string $from_name
 * @property string|null $from_address
 * @property string $to_name
 * @property string|null $to_address
 * @property CarbonImmutable|null $posted_at
 * @property CarbonImmutable|null $arrived_at
 * @property-read FidonetSource $source
 * @property-read FidonetMessageCleanup|null $cleanup
 */
class FidonetMessage extends Model
{
    protected $table = 'fidonet_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'posted_at' => 'immutable_datetime',
            'arrived_at' => 'immutable_datetime',
            'raw_metadata_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FidonetSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FidonetSource::class, 'fidonet_source_id');
    }

    /**
     * @return HasOne<FidonetMessageCleanup, $this>
     */
    public function cleanup(): HasOne
    {
        return $this->hasOne(FidonetMessageCleanup::class, 'canonical_message_id', 'canonical_message_id');
    }
}
