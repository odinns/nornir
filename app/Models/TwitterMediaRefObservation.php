<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $twitter_media_ref_id
 * @property int $twitter_archive_id
 * @property string $source
 * @property-read TwitterMediaRef $mediaRef
 * @property-read TwitterArchive $archive
 */
class TwitterMediaRefObservation extends Model
{
    protected $table = 'twitter_media_ref_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<TwitterMediaRef, $this>
     */
    public function mediaRef(): BelongsTo
    {
        return $this->belongsTo(TwitterMediaRef::class, 'twitter_media_ref_id');
    }

    /**
     * @return BelongsTo<TwitterArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(TwitterArchive::class, 'twitter_archive_id');
    }
}
