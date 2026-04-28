<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fidonet_source_id
 * @property string $canonical_message_id
 * @property-read FidonetSource $source
 * @property-read FidonetMessage|null $message
 */
class FidonetMessageObservation extends Model
{
    protected $table = 'fidonet_message_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<FidonetSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FidonetSource::class, 'fidonet_source_id');
    }

    /**
     * @return BelongsTo<FidonetMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(FidonetMessage::class, 'canonical_message_id', 'canonical_message_id');
    }
}
