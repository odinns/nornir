<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $apple_messages_message_id
 * @property string $attachment_key
 * @property string|null $source_guid
 * @property string|null $relative_path
 * @property int|null $total_bytes
 * @property array<string, mixed>|null $raw_attachment
 * @property-read AppleMessagesMessage $message
 */
class AppleMessagesAttachment extends Model
{
    protected $table = 'apple_messages_attachments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_bytes' => 'integer',
            'raw_attachment' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AppleMessagesMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AppleMessagesMessage::class, 'apple_messages_message_id');
    }
}
