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
 * @property int $chatgpt_archive_id
 * @property string $conversation_id
 * @property string|null $title
 * @property string|null $current_node
 * @property float|null $source_create_time
 * @property float|null $source_update_time
 * @property CarbonImmutable|null $conversation_created_at
 * @property CarbonImmutable|null $conversation_updated_at
 * @property array<string, mixed>|null $raw_metadata
 * @property-read ChatGptArchive $archive
 * @property-read Collection<int, ChatGptNode> $nodes
 * @property-read Collection<int, ChatGptMessage> $messages
 * @property-read Collection<int, ChatGptConversationObservation> $observations
 */
class ChatGptConversation extends Model
{
    protected $table = 'chatgpt_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_create_time' => 'float',
            'source_update_time' => 'float',
            'conversation_created_at' => 'immutable_datetime',
            'conversation_updated_at' => 'immutable_datetime',
            'raw_metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ChatGptArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(ChatGptArchive::class, 'chatgpt_archive_id');
    }

    /**
     * @return HasMany<ChatGptNode, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(ChatGptNode::class, 'chatgpt_conversation_id');
    }

    /**
     * @return HasMany<ChatGptMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatGptMessage::class, 'chatgpt_conversation_id');
    }

    /**
     * @return HasMany<ChatGptConversationObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(ChatGptConversationObservation::class, 'chatgpt_conversation_id');
    }
}
