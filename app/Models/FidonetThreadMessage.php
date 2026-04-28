<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fidonet_thread_id
 * @property string $canonical_message_id
 * @property int $thread_order
 * @property-read FidonetThread $thread
 * @property-read FidonetMessage|null $message
 */
class FidonetThreadMessage extends Model
{
    protected $table = 'fidonet_thread_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'thread_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FidonetThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(FidonetThread::class, 'fidonet_thread_id');
    }

    /**
     * @return BelongsTo<FidonetMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(FidonetMessage::class, 'canonical_message_id', 'canonical_message_id');
    }
}
