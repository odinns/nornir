<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $twitter_note_tweet_id
 * @property int $twitter_archive_id
 * @property string $source
 * @property-read TwitterNoteTweet $noteTweet
 * @property-read TwitterArchive $archive
 */
class TwitterNoteTweetObservation extends Model
{
    protected $table = 'twitter_note_tweet_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<TwitterNoteTweet, $this>
     */
    public function noteTweet(): BelongsTo
    {
        return $this->belongsTo(TwitterNoteTweet::class, 'twitter_note_tweet_id');
    }

    /**
     * @return BelongsTo<TwitterArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(TwitterArchive::class, 'twitter_archive_id');
    }
}
