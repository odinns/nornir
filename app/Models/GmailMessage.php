<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property int $gmail_thread_id
 * @property string $message_id
 * @property string|null $from_header
 * @property string|null $to_header
 * @property string|null $cc_header
 * @property string|null $subject
 * @property string|null $snippet
 * @property string|null $body_plain
 * @property string|null $body_html
 * @property array<int, array{name?: string, value?: string}>|null $raw_headers
 * @property int|null $internal_date
 * @property CarbonImmutable|null $message_received_at
 * @property array<string, mixed>|null $raw_payload
 * @property-read GmailThread $thread
 * @property-read Collection<int, GmailMessageLabel> $labels
 * @property-read Collection<int, GmailAttachment> $attachments
 */
class GmailMessage extends Model
{
    protected $table = 'gmail_messages';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'raw_headers' => 'array',
            'internal_date' => 'integer',
            'message_received_at' => 'immutable_datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<GmailThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(GmailThread::class, 'gmail_thread_id');
    }

    /**
     * @return HasMany<GmailMessageLabel, $this>
     */
    public function labels(): HasMany
    {
        return $this->hasMany(GmailMessageLabel::class, 'gmail_message_id');
    }

    /**
     * @return HasMany<GmailAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(GmailAttachment::class, 'gmail_message_id');
    }
}
