<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gmail_message_id
 * @property int $gmail_source_set_id
 * @property-read GmailMessage $message
 * @property-read GmailSourceSet $sourceSet
 */
class GmailMessageObservation extends Model
{
    protected $table = 'gmail_message_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<GmailMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(GmailMessage::class, 'gmail_message_id');
    }

    /**
     * @return BelongsTo<GmailSourceSet, $this>
     */
    public function sourceSet(): BelongsTo
    {
        return $this->belongsTo(GmailSourceSet::class, 'gmail_source_set_id');
    }
}
