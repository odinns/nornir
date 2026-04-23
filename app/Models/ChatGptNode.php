<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $chatgpt_conversation_id
 * @property string $node_id
 * @property string|null $parent_node_id
 * @property array<int, string>|null $child_node_ids
 * @property array<string, mixed>|null $raw_node
 * @property-read ChatGptConversation $conversation
 * @property-read ChatGptMessage|null $message
 */
class ChatGptNode extends Model
{
    protected $table = 'chatgpt_nodes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'child_node_ids' => 'array',
            'raw_node' => 'array',
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
     * @return HasOne<ChatGptMessage, $this>
     */
    public function message(): HasOne
    {
        return $this->hasOne(ChatGptMessage::class, 'chatgpt_node_id');
    }
}
