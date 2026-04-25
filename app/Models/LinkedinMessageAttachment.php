<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, string> $attachment_urls_json
 */
class LinkedinMessageAttachment extends Model
{
    protected $table = 'linkedin_message_attachments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attachment_urls_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LinkedinMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(LinkedinMessage::class, 'linkedin_message_id');
    }
}
