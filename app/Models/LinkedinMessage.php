<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Override;

/**
 * @property string|null $content
 * @property CarbonImmutable|null $sent_at
 * @property array<string, mixed>|null $raw_message
 */
class LinkedinMessage extends Model
{
    protected $table = 'linkedin_messages';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'raw_message' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LinkedinConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(LinkedinConversation::class, 'linkedin_conversation_id');
    }

    /**
     * @return BelongsTo<LinkedinArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(LinkedinArchive::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return BelongsTo<LinkedinPerson, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(LinkedinPerson::class, 'sender_linkedin_person_id');
    }

    /**
     * @return HasOne<LinkedinMessageAttachment, $this>
     */
    public function attachment(): HasOne
    {
        return $this->hasOne(LinkedinMessageAttachment::class, 'linkedin_message_id');
    }
}
