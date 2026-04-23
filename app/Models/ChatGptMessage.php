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
 * @property int $chatgpt_conversation_id
 * @property int $chatgpt_node_id
 * @property string $message_id
 * @property string|null $author_role
 * @property string|null $author_name
 * @property string|null $content_type
 * @property string|null $status
 * @property string|null $recipient
 * @property string|null $model_slug
 * @property float|null $source_create_time
 * @property float|null $source_update_time
 * @property bool|null $end_turn
 * @property CarbonImmutable|null $message_created_at
 * @property CarbonImmutable|null $message_updated_at
 * @property array<string, mixed>|null $raw_message
 * @property-read ChatGptConversation $conversation
 * @property-read ChatGptNode $node
 * @property-read Collection<int, ChatGptMessagePart> $parts
 * @property-read Collection<int, ChatGptAsset> $assets
 * @property-read Collection<int, ChatGptMessageObservation> $observations
 */
class ChatGptMessage extends Model
{
    protected $table = 'chatgpt_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_create_time' => 'float',
            'source_update_time' => 'float',
            'end_turn' => 'boolean',
            'message_created_at' => 'immutable_datetime',
            'message_updated_at' => 'immutable_datetime',
            'raw_message' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ChatGptConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatGptConversation::class, 'chatgpt_conversation_id');
    }

    /**
     * @return BelongsTo<ChatGptNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(ChatGptNode::class, 'chatgpt_node_id');
    }

    /**
     * @return HasMany<ChatGptMessagePart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(ChatGptMessagePart::class, 'chatgpt_message_id');
    }

    /**
     * @return HasMany<ChatGptAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(ChatGptAsset::class, 'chatgpt_message_id');
    }

    /**
     * @return HasMany<ChatGptMessageObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(ChatGptMessageObservation::class, 'chatgpt_message_id');
    }
}
