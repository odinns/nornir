<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $facebook_message_id
 * @property int|null $facebook_post_id
 * @property string $attachment_key
 * @property string $source_context
 * @property string $attachment_type
 * @property int|null $created_timestamp
 * @property int|null $file_size_bytes
 * @property array<string, mixed>|null $raw_attachment
 * @property-read FacebookMessage|null $message
 * @property-read FacebookPost|null $post
 */
class FacebookAttachment extends Model
{
    protected $table = 'facebook_attachments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_timestamp' => 'integer',
            'file_size_bytes' => 'integer',
            'raw_attachment' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FacebookMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(FacebookMessage::class, 'facebook_message_id');
    }

    /**
     * @return BelongsTo<FacebookPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(FacebookPost::class, 'facebook_post_id');
    }
}
