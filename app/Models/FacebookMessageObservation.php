<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facebook_message_id
 * @property int $facebook_archive_id
 * @property-read FacebookMessage $message
 * @property-read FacebookArchive $archive
 */
class FacebookMessageObservation extends Model
{
    protected $table = 'facebook_message_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<FacebookMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(FacebookMessage::class, 'facebook_message_id');
    }

    /**
     * @return BelongsTo<FacebookArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(FacebookArchive::class, 'facebook_archive_id');
    }
}
