<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chatgpt_message_id
 * @property int $chatgpt_source_set_id
 * @property int|null $chatgpt_archive_id
 * @property-read ChatGptMessage $message
 * @property-read ChatGptSourceSet $sourceSet
 * @property-read ChatGptArchive|null $archive
 */
class ChatGptMessageObservation extends Model
{
    protected $table = 'chatgpt_message_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<ChatGptMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatGptMessage::class, 'chatgpt_message_id');
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
