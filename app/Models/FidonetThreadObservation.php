<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fidonet_source_id
 * @property int $fidonet_thread_id
 * @property-read FidonetSource $source
 * @property-read FidonetThread $thread
 */
class FidonetThreadObservation extends Model
{
    protected $table = 'fidonet_thread_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<FidonetSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FidonetSource::class, 'fidonet_source_id');
    }

    /**
     * @return BelongsTo<FidonetThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(FidonetThread::class, 'fidonet_thread_id');
    }
}
