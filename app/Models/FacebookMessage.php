<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $facebook_thread_id
 * @property int|null $sender_facebook_person_id
 * @property string $canonical_key
 * @property int $timestamp_ms
 * @property CarbonImmutable|null $sent_at
 * @property string|null $content
 * @property bool $is_unsent
 * @property-read FacebookThread $thread
 * @property-read FacebookPerson|null $sender
 * @property-read Collection<int, FacebookMessageObservation> $observations
 * @property-read Collection<int, FacebookMessageReaction> $messageReactions
 * @property-read Collection<int, FacebookAttachment> $attachments
 */
class FacebookMessage extends Model
{
    protected $table = 'facebook_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'timestamp_ms' => 'integer',
            'sent_at' => 'immutable_datetime',
            'is_unsent' => 'boolean',
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

    /**
     * @return HasMany<FacebookMessageObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(FacebookMessageObservation::class, 'facebook_message_id');
    }

    /**
     * @return HasMany<FacebookMessageReaction, $this>
     */
    public function messageReactions(): HasMany
    {
        return $this->hasMany(FacebookMessageReaction::class, 'facebook_message_id');
    }

    /**
     * @return HasMany<FacebookAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(FacebookAttachment::class, 'facebook_message_id');
    }
}
