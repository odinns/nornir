<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facebook_thread_id
 * @property int|null $sender_facebook_person_id
 * @property string $canonical_key
 * @property CarbonImmutable|null $sent_at
 * @property string|null $content
 * @property-read FacebookThread $thread
 * @property-read FacebookPerson|null $sender
 */
class FacebookMessage extends Model
{
    protected $table = 'facebook_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'raw_message' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FacebookThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(FacebookThread::class, 'facebook_thread_id');
    }

    /**
     * @return BelongsTo<FacebookPerson, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(FacebookPerson::class, 'sender_facebook_person_id');
    }
}
