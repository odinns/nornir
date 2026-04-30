<?php

declare(strict_types=1);

use App\Models\TwitterAccount;
use App\Models\TwitterArchive;
use App\Models\TwitterMediaRef;
use App\Models\TwitterMediaRefObservation;
use App\Models\TwitterNoteTweet;
use App\Models\TwitterNoteTweetObservation;
use App\Models\TwitterProfileSnapshot;
use App\Models\TwitterScreenNameChange;
use App\Models\TwitterTweet;
use App\Models\TwitterTweetObservation;
use Carbon\CarbonImmutable;

it('maps twitter importer tables through explicit eloquent model contracts', function (): void {
    $archive = new TwitterArchive([
        'archive_generated_at' => '2026-02-17 06:30:43',
        'raw_manifest' => ['archiveInfo' => ['generationDate' => '2026-02-17T06:30:43.000Z']],
    ]);
    $account = new TwitterAccount([
        'account_created_at' => '2023-10-01 04:12:34',
        'raw_account' => ['accountId' => '123456'],
    ]);
    $profileSnapshot = new TwitterProfileSnapshot([
        'is_verified' => 1,
        'is_verified_organization' => 0,
        'raw_profile' => ['profile' => ['screenName' => 'odinn']],
    ]);
    $screenNameChange = new TwitterScreenNameChange([
        'changed_at' => '2023-10-01 04:12:34',
        'raw_change' => ['screenNameChange' => ['changedTo' => 'odinns_art']],
    ]);
    $tweet = new TwitterTweet([
        'tweeted_at' => '2026-02-17 06:30:43',
        'retweet_count' => '2',
        'reply_count' => '3',
        'like_count' => '4',
        'quote_count' => '5',
        'bookmark_count' => '6',
        'raw_tweet' => ['id_str' => '111'],
    ]);
    $noteTweet = new TwitterNoteTweet([
        'tweeted_at' => '2026-02-17 06:30:43',
        'raw_note_tweet' => ['noteTweetId' => 'note-111'],
    ]);
    $mediaRef = new TwitterMediaRef([
        'raw_media' => ['media_key' => 'profile:avatar'],
    ]);
    $tweetObservation = new TwitterTweetObservation([
        'source' => 'import',
    ]);
    $noteTweetObservation = new TwitterNoteTweetObservation([
        'source' => 'import',
    ]);
    $mediaRefObservation = new TwitterMediaRefObservation([
        'source' => 'import',
    ]);

    expect($archive->getTable())->toBe('twitter_archives')
        ->and($archive->archive_generated_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($archive->raw_manifest)->toBeArray()
        ->and($archive->account()->getForeignKeyName())->toBe('twitter_archive_id')
        ->and($archive->profileSnapshot()->getForeignKeyName())->toBe('twitter_archive_id')
        ->and($archive->screenNameChanges()->getForeignKeyName())->toBe('twitter_archive_id')
        ->and($archive->mediaRefs()->getForeignKeyName())->toBe('twitter_archive_id')
        ->and($archive->tweets()->getForeignKeyName())->toBe('first_seen_twitter_archive_id')
        ->and($archive->noteTweets()->getForeignKeyName())->toBe('first_seen_twitter_archive_id')
        ->and($archive->tweetObservations()->getForeignKeyName())->toBe('twitter_archive_id')
        ->and($archive->noteTweetObservations()->getForeignKeyName())->toBe('twitter_archive_id')
        ->and($archive->mediaRefObservations()->getForeignKeyName())->toBe('twitter_archive_id');

    expect($account->getTable())->toBe('twitter_accounts')
        ->and($account->account_created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($account->raw_account)->toBeArray()
        ->and($account->archive()->getForeignKeyName())->toBe('twitter_archive_id');

    expect($profileSnapshot->getTable())->toBe('twitter_profile_snapshots')
        ->and($profileSnapshot->is_verified)->toBeTrue()
        ->and($profileSnapshot->is_verified_organization)->toBeFalse()
        ->and($profileSnapshot->raw_profile)->toBeArray()
        ->and($profileSnapshot->archive()->getForeignKeyName())->toBe('twitter_archive_id');

    expect($screenNameChange->getTable())->toBe('twitter_screen_name_changes')
        ->and($screenNameChange->changed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($screenNameChange->raw_change)->toBeArray()
        ->and($screenNameChange->archive()->getForeignKeyName())->toBe('twitter_archive_id');

    expect($tweet->getTable())->toBe('twitter_tweets')
        ->and($tweet->tweeted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($tweet->retweet_count)->toBe(2)
        ->and($tweet->reply_count)->toBe(3)
        ->and($tweet->like_count)->toBe(4)
        ->and($tweet->quote_count)->toBe(5)
        ->and($tweet->bookmark_count)->toBe(6)
        ->and($tweet->raw_tweet)->toBeArray()
        ->and($tweet->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_twitter_archive_id')
        ->and($tweet->observations()->getForeignKeyName())->toBe('twitter_tweet_id');

    expect($noteTweet->getTable())->toBe('twitter_note_tweets')
        ->and($noteTweet->tweeted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($noteTweet->raw_note_tweet)->toBeArray()
        ->and($noteTweet->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_twitter_archive_id')
        ->and($noteTweet->observations()->getForeignKeyName())->toBe('twitter_note_tweet_id');

    expect($mediaRef->getTable())->toBe('twitter_media_refs')
        ->and($mediaRef->raw_media)->toBeArray()
        ->and($mediaRef->archive()->getForeignKeyName())->toBe('twitter_archive_id')
        ->and($mediaRef->observations()->getForeignKeyName())->toBe('twitter_media_ref_id');

    expect($tweetObservation->getTable())->toBe('twitter_tweet_observations')
        ->and($tweetObservation->tweet()->getForeignKeyName())->toBe('twitter_tweet_id')
        ->and($tweetObservation->archive()->getForeignKeyName())->toBe('twitter_archive_id');

    expect($noteTweetObservation->getTable())->toBe('twitter_note_tweet_observations')
        ->and($noteTweetObservation->noteTweet()->getForeignKeyName())->toBe('twitter_note_tweet_id')
        ->and($noteTweetObservation->archive()->getForeignKeyName())->toBe('twitter_archive_id');

    expect($mediaRefObservation->getTable())->toBe('twitter_media_ref_observations')
        ->and($mediaRefObservation->mediaRef()->getForeignKeyName())->toBe('twitter_media_ref_id')
        ->and($mediaRefObservation->archive()->getForeignKeyName())->toBe('twitter_archive_id');
});
