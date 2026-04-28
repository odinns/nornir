<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $apple_messages_conversation_id
 * @property int|null $sender_participant_id
 * @property string $canonical_key
 * @property string|null $source_guid
 * @property int|null $source_row_id
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $delivered_at
 * @property bool $from_me
 * @property string|null $service
 * @property string|null $text_body
 * @property bool $is_delivered
 * @property bool $is_read
 * @property bool $is_sent
 * @property int $item_type
 * @property string|null $group_title
 * @property int|null $group_action_type
 * @property string|null $reaction_to_guid
 * @property int|null $reaction_type
 * @property array<string, mixed>|null $raw_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AppleMessagesAttachment> $attachments
 * @property-read Collection<int, AppleMessagesMessageObservation> $observations
 * @property-read AppleMessagesConversation $conversation
 * @property-read AppleMessagesParticipant|null $sender
 */
class AppleMessagesMessage extends Model
{
    protected $table = 'apple_messages_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'source_row_id' => 'integer',
            'from_me' => 'boolean',
            'is_delivered' => 'boolean',
            'is_read' => 'boolean',
            'is_sent' => 'boolean',
            'item_type' => 'integer',
            'group_action_type' => 'integer',
            'reaction_type' => 'integer',
            'raw_message' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AppleMessagesConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AppleMessagesConversation::class, 'apple_messages_conversation_id');
    }

    /**
     * @return BelongsTo<AppleMessagesParticipant, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(AppleMessagesParticipant::class, 'sender_participant_id');
    }

    /**
     * @return HasMany<AppleMessagesAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AppleMessagesAttachment::class, 'apple_messages_message_id');
    }

    /**
     * @return HasMany<AppleMessagesMessageObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(AppleMessagesMessageObservation::class, 'apple_messages_message_id');
    }
}
