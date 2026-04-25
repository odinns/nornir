<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $message_count
 * @property string|null $title
 * @property CarbonImmutable|null $first_message_at
 * @property CarbonImmutable|null $last_message_at
 * @property-read Collection<int, LinkedinMessage> $messages
 */
class LinkedinConversation extends Model
{
    protected $table = 'linkedin_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
            'first_message_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<LinkedinArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(LinkedinArchive::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(LinkedinMessage::class, 'linkedin_conversation_id');
    }
}
