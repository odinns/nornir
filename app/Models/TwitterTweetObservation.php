<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $twitter_tweet_id
 * @property int $twitter_archive_id
 * @property string $source
 * @property-read TwitterTweet $tweet
 * @property-read TwitterArchive $archive
 */
class TwitterTweetObservation extends Model
{
    protected $table = 'twitter_tweet_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<TwitterTweet, $this>
     */
    public function tweet(): BelongsTo
    {
        return $this->belongsTo(TwitterTweet::class, 'twitter_tweet_id');
    }

    /**
     * @return BelongsTo<TwitterArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(TwitterArchive::class, 'twitter_archive_id');
    }
}
