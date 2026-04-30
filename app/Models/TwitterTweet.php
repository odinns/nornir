<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $first_seen_twitter_archive_id
 * @property string|null $account_id
 * @property string $tweet_id
 * @property string $source_surface
 * @property string|null $created_at_source
 * @property CarbonImmutable|null $tweeted_at
 * @property string|null $full_text
 * @property string|null $source_client
 * @property string|null $lang
 * @property string|null $conversation_id
 * @property string|null $in_reply_to_tweet_id
 * @property string|null $in_reply_to_user_id
 * @property int|null $retweet_count
 * @property int|null $reply_count
 * @property int|null $like_count
 * @property int|null $quote_count
 * @property int|null $bookmark_count
 * @property array<string, mixed>|null $raw_tweet
 * @property-read Collection<int, TwitterTweetObservation> $observations
 */
class TwitterTweet extends Model
{
    protected $table = 'twitter_tweets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tweeted_at' => 'immutable_datetime',
            'retweet_count' => 'integer',
            'reply_count' => 'integer',
            'like_count' => 'integer',
            'quote_count' => 'integer',
            'bookmark_count' => 'integer',
            'raw_tweet' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TwitterArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(TwitterArchive::class, 'first_seen_twitter_archive_id');
    }

    /**
     * @return HasMany<TwitterTweetObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(TwitterTweetObservation::class, 'twitter_tweet_id');
    }
}
