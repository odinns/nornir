<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $apple_messages_conversation_id
 * @property int|null $sender_participant_id
 * @property string $canonical_key
 * @property CarbonImmutable|null $sent_at
 * @property bool $from_me
 * @property string|null $service
 * @property string|null $text_body
 * @property string|null $group_title
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
            'from_me' => 'boolean',
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
}
