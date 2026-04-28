<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facebook_comment_id
 * @property int $facebook_archive_id
 * @property-read FacebookComment $comment
 * @property-read FacebookArchive $archive
 */
class FacebookCommentObservation extends Model
{
    protected $table = 'facebook_comment_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<FacebookComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(FacebookComment::class, 'facebook_comment_id');
    }

    /**
     * @return BelongsTo<FacebookArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(FacebookArchive::class, 'facebook_archive_id');
    }
}
