<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $archive_key
 * @property string $source_locator
 * @property string $access_mode
 * @property string|null $account_id
 * @property string|null $username
 * @property string|null $archive_generated_at_source
 * @property CarbonImmutable|null $archive_generated_at
 * @property array<string, mixed>|null $raw_manifest
 * @property-read TwitterAccount|null $account
 * @property-read TwitterProfileSnapshot|null $profileSnapshot
 * @property-read Collection<int, TwitterScreenNameChange> $screenNameChanges
 * @property-read Collection<int, TwitterMediaRef> $mediaRefs
 * @property-read Collection<int, TwitterTweet> $tweets
 * @property-read Collection<int, TwitterNoteTweet> $noteTweets
 * @property-read Collection<int, TwitterTweetObservation> $tweetObservations
 * @property-read Collection<int, TwitterNoteTweetObservation> $noteTweetObservations
 * @property-read Collection<int, TwitterMediaRefObservation> $mediaRefObservations
 */
class TwitterArchive extends Model
{
    protected $table = 'twitter_archives';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'archive_generated_at' => 'immutable_datetime',
            'raw_manifest' => 'array',
        ];
    }

    /**
     * @return HasOne<TwitterAccount, $this>
     */
    public function account(): HasOne
    {
        return $this->hasOne(TwitterAccount::class);
    }

    /**
     * @return HasOne<TwitterProfileSnapshot, $this>
     */
    public function profileSnapshot(): HasOne
    {
        return $this->hasOne(TwitterProfileSnapshot::class);
    }

    /**
     * @return HasMany<TwitterScreenNameChange, $this>
     */
    public function screenNameChanges(): HasMany
    {
        return $this->hasMany(TwitterScreenNameChange::class);
    }

    /**
     * @return HasMany<TwitterMediaRef, $this>
     */
    public function mediaRefs(): HasMany
    {
        return $this->hasMany(TwitterMediaRef::class);
    }

    /**
     * @return HasMany<TwitterTweet, $this>
     */
    public function tweets(): HasMany
    {
        return $this->hasMany(TwitterTweet::class, 'first_seen_twitter_archive_id');
    }

    /**
     * @return HasMany<TwitterNoteTweet, $this>
     */
    public function noteTweets(): HasMany
    {
        return $this->hasMany(TwitterNoteTweet::class, 'first_seen_twitter_archive_id');
    }

    /**
     * @return HasMany<TwitterTweetObservation, $this>
     */
    public function tweetObservations(): HasMany
    {
        return $this->hasMany(TwitterTweetObservation::class, 'twitter_archive_id');
    }

    /**
     * @return HasMany<TwitterNoteTweetObservation, $this>
     */
    public function noteTweetObservations(): HasMany
    {
        return $this->hasMany(TwitterNoteTweetObservation::class, 'twitter_archive_id');
    }

    /**
     * @return HasMany<TwitterMediaRefObservation, $this>
     */
    public function mediaRefObservations(): HasMany
    {
        return $this->hasMany(TwitterMediaRefObservation::class, 'twitter_archive_id');
    }
}
