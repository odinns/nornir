<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gmail_message_id
 * @property string $label_id
 * @property string|null $label_name
 * @property-read GmailMessage $message
 */
class GmailMessageLabel extends Model
{
    protected $table = 'gmail_message_labels';

    protected $guarded = [];

    /**
     * @return BelongsTo<GmailMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(GmailMessage::class, 'gmail_message_id');
    }
}
