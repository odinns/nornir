<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $conversation_key
 * @property string|null $source_guid
 * @property string|null $display_name
 * @property string|null $room_name
 * @property string|null $chat_identifier
 * @property string|null $service
 * @property int|null $style
 * @property bool $is_archived
 * @property array<string, mixed>|null $raw_chat
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AppleMessagesMessage> $messages
 * @property-read Collection<int, AppleMessagesParticipant> $participants
 */
class AppleMessagesConversation extends Model
{
    protected $table = 'apple_messages_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'style' => 'integer',
            'is_archived' => 'boolean',
            'raw_chat' => 'array',
        ];
    }

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
