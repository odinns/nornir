<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $apple_messages_message_id
 * @property string $attachment_key
 * @property string|null $source_guid
 * @property string|null $relative_path
 * @property string|null $source_filename
 * @property string|null $mime_type
 * @property string|null $transfer_name
 * @property int|null $total_bytes
 * @property array<string, mixed>|null $raw_attachment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
