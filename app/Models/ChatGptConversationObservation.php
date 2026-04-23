<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chatgpt_conversation_id
 * @property int $chatgpt_source_set_id
 * @property int|null $chatgpt_archive_id
 * @property-read ChatGptConversation $conversation
 * @property-read ChatGptSourceSet $sourceSet
 * @property-read ChatGptArchive|null $archive
 */
class ChatGptConversationObservation extends Model
{
    protected $table = 'chatgpt_conversation_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<ChatGptConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatGptConversation::class, 'chatgpt_conversation_id');
    }

    /**
     * @return BelongsTo<ChatGptSourceSet, $this>
     */
    public function sourceSet(): BelongsTo
    {
        return $this->belongsTo(ChatGptSourceSet::class, 'chatgpt_source_set_id');
    }

    /**
     * @return BelongsTo<ChatGptArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(ChatGptArchive::class, 'chatgpt_archive_id');
    }
}
