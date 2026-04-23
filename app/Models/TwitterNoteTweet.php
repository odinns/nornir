<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $first_seen_twitter_archive_id
 * @property string|null $account_id
 * @property string $note_tweet_id
 * @property string|null $created_at_source
 * @property CarbonImmutable|null $tweeted_at
 * @property string|null $full_text
 * @property array<string, mixed>|null $raw_note_tweet
 */
class TwitterNoteTweet extends Model
{
    protected $table = 'twitter_note_tweets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tweeted_at' => 'immutable_datetime',
            'raw_note_tweet' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TwitterArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(TwitterArchive::class, 'first_seen_twitter_archive_id');
    }
}
