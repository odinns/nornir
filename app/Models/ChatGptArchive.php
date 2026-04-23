<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $chatgpt_source_set_id
 * @property string $archive_key
 * @property string $source_locator
 * @property string $source_file
 * @property string|null $archive_label
 * @property-read ChatGptSourceSet|null $sourceSet
 * @property-read Collection<int, ChatGptConversation> $conversations
 * @property-read Collection<int, ChatGptConversationObservation> $conversationObservations
 * @property-read Collection<int, ChatGptMessageObservation> $messageObservations
 */
class ChatGptArchive extends Model
{
    protected $table = 'chatgpt_archives';

    protected $guarded = [];

    /**
     * @return BelongsTo<ChatGptSourceSet, $this>
     */
    public function sourceSet(): BelongsTo
    {
        return $this->belongsTo(ChatGptSourceSet::class, 'chatgpt_source_set_id');
    }

    /**
     * @return HasMany<ChatGptConversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(ChatGptConversation::class, 'chatgpt_archive_id');
    }

    /**
     * @return HasMany<ChatGptConversationObservation, $this>
     */
    public function conversationObservations(): HasMany
    {
        return $this->hasMany(ChatGptConversationObservation::class, 'chatgpt_archive_id');
    }

    /**
     * @return HasMany<ChatGptMessageObservation, $this>
     */
    public function messageObservations(): HasMany
    {
        return $this->hasMany(ChatGptMessageObservation::class, 'chatgpt_archive_id');
    }
}
