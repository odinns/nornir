<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $conversation_key
 * @property string|null $display_name
 * @property string|null $room_name
 * @property string|null $chat_identifier
 * @property-read Collection<int, AppleMessagesMessage> $messages
 * @property-read Collection<int, AppleMessagesParticipant> $participants
 */
class AppleMessagesConversation extends Model
{
    protected $table = 'apple_messages_conversations';

    protected $guarded = [];

    /**
     * @return HasMany<AppleMessagesMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AppleMessagesMessage::class, 'apple_messages_conversation_id');
    }

    /**
     * @return BelongsToMany<AppleMessagesParticipant, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(
            AppleMessagesParticipant::class,
            'apple_messages_conversation_participant',
            'apple_messages_conversation_id',
            'apple_messages_participant_id',
        );
    }
}
