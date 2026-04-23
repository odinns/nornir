<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $source_key
 * @property string $source_locator
 * @property string $access_mode
 * @property-read Collection<int, ChatGptArchive> $archives
 * @property-read Collection<int, ChatGptConversationObservation> $conversationObservations
 * @property-read Collection<int, ChatGptMessageObservation> $messageObservations
 */
class ChatGptSourceSet extends Model
{
    protected $table = 'chatgpt_source_sets';

    protected $guarded = [];

    /**
     * @return HasMany<ChatGptArchive, $this>
     */
    public function archives(): HasMany
    {
        return $this->hasMany(ChatGptArchive::class, 'chatgpt_source_set_id');
    }

    /**
     * @return HasMany<ChatGptConversationObservation, $this>
     */
    public function conversationObservations(): HasMany
    {
        return $this->hasMany(ChatGptConversationObservation::class, 'chatgpt_source_set_id');
    }

    /**
     * @return HasMany<ChatGptMessageObservation, $this>
     */
    public function messageObservations(): HasMany
    {
        return $this->hasMany(ChatGptMessageObservation::class, 'chatgpt_source_set_id');
    }
}
