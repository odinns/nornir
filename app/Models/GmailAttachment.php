<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $gmail_message_id
 * @property string $attachment_id
 * @property string|null $filename
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property-read GmailMessage $message
 */
class GmailAttachment extends Model
{
    protected $table = 'gmail_attachments';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GmailMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(GmailMessage::class, 'gmail_message_id');
    }
}
