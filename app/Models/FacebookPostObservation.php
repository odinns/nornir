<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facebook_post_id
 * @property int $facebook_archive_id
 * @property-read FacebookPost $post
 * @property-read FacebookArchive $archive
 */
class FacebookPostObservation extends Model
{
    protected $table = 'facebook_post_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<FacebookPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(FacebookPost::class, 'facebook_post_id');
    }

    /**
     * @return BelongsTo<FacebookArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(FacebookArchive::class, 'facebook_archive_id');
    }
}
