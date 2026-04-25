<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  array{
 *     manifest?:array<string, mixed>,
 *     account?:array<string, mixed>,
 *     profile?:array<string, mixed>,
 *     tweet_headers?:list<array<string, mixed>>,
 *     tweets?:list<array<string, mixed>>,
 *     community_tweets?:list<array<string, mixed>>,
 *     note_tweets?:list<array<string, mixed>>,
 *     screen_name_changes?:list<array<string, mixed>>,
 *     verified?:array<string, mixed>|null,
 *     verified_organization?:array<string, mixed>|null,
 *     include_profile?:bool,
 *     include_tweet_headers?:bool,
 *     include_community_tweets?:bool,
 *     include_note_tweets?:bool,
 *     include_screen_name_changes?:bool,
 *     include_verified?:bool,
 *     include_verified_organization?:bool,
 *     malformed_files?:array<string, string>
 * }  $dataset
 * @return array{root_path:string, archive_path:string}
 */
function createTwitterFixtureArchive(string $name, array $dataset = []): array
{
    $root = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));
    $archivePath = $root.'/twitter';
    $dataPath = $archivePath.'/data';

    File::ensureDirectoryExists($dataPath);

    $manifest = $dataset['manifest'] ?? [
        'archiveInfo' => [
            'generationDate' => '2026-02-20T10:00:00.000Z',
        ],
        'userInfo' => [
            'accountId' => '123456',
            'userName' => 'odinn',
        ],
    ];

    $account = $dataset['account'] ?? [
        'accountId' => '123456',
        'username' => 'odinn',
        'accountDisplayName' => 'Odinn Test',
        'createdAt' => '2010-01-01T00:00:00.000Z',
    ];

    $profile = $dataset['profile'] ?? [
        'screenName' => 'odinn',
        'displayName' => 'Odinn Test',
        'description' => [
            'bio' => 'Building strange little systems.',
            'website' => 'https://example.test',
        ],
        'location' => 'Copenhagen',
        'avatarMediaUrl' => 'profile_media/avatar.jpg',
        'headerMediaUrl' => 'profile_media/header.jpg',
    ];

    $tweets = $dataset['tweets'] ?? [[
        'id_str' => '111',
        'created_at' => 'Tue Feb 17 06:30:43 +0000 2026',
        'full_text' => 'Hello from the importer',
        'source' => 'Twitter Web App',
        'lang' => 'en',
        'conversation_id_str' => '111',
        'retweet_count' => '2',
        'reply_count' => '1',
        'like_count' => '5',
        'quote_count' => '0',
        'bookmark_count' => '0',
        'entities' => [
            'media' => [[
                'media_url_https' => 'https://pbs.twimg.com/media/tweet-photo-1.jpg',
                'expanded_url' => 'https://x.com/odinn/status/111/photo/1',
                'url' => 'https://t.co/abc123',
                'type' => 'photo',
                'media_path' => 'tweets_media/tweet-photo-1.jpg',
            ]],
        ],
    ]];

    $communityTweets = $dataset['community_tweets'] ?? [[
        'id_str' => '112',
        'created_at' => 'Wed Feb 18 06:30:43 +0000 2026',
        'full_text' => 'Community post',
        'source' => 'Twitter for iPhone',
        'lang' => 'en',
        'conversation_id_str' => '112',
        'reply_count' => '0',
        'retweet_count' => '0',
        'like_count' => '3',
        'quote_count' => '0',
        'bookmark_count' => '0',
        'entities' => [
            'media' => [[
                'media_url_https' => 'https://pbs.twimg.com/media/community-photo-1.jpg',
                'expanded_url' => 'https://x.com/i/communities/1/post/112/photo/1',
                'url' => 'https://t.co/def456',
                'type' => 'photo',
                'media_path' => 'community_tweet_media/community-photo-1.jpg',
            ]],
        ],
    ]];

    $noteTweets = $dataset['note_tweets'] ?? [[
        'noteTweetId' => 'note-1',
        'createdAt' => '2026-02-19T06:30:43.000Z',
        'core' => [
            'text' => 'Longer note tweet text',
        ],
    ]];

    $screenNameChanges = $dataset['screen_name_changes'] ?? [[
        'screenNameChange' => [
            'changedAt' => '2024-01-01T00:00:00.000Z',
            'screenName' => 'oldodinn',
        ],
    ]];

    $tweetHeaders = $dataset['tweet_headers'] ?? array_map(
        static fn (array $tweet): array => ['tweet' => ['id_str' => (string) ($tweet['id_str'] ?? '')]],
        [...$tweets, ...$communityTweets]
    );

    writeTwitterWrappedFile($dataPath.'/manifest.js', '__THAR_CONFIG', $manifest, $dataset['malformed_files']['manifest.js'] ?? null);
    writeTwitterWrappedFile($dataPath.'/account.js', 'account', [['account' => $account]], $dataset['malformed_files']['account.js'] ?? null);
    writeTwitterWrappedFile($dataPath.'/tweets.js', 'tweets', array_map(
        static fn (array $tweet): array => ['tweet' => $tweet],
        $tweets
    ), $dataset['malformed_files']['tweets.js'] ?? null);

    if (($dataset['include_profile'] ?? true) === true) {
        writeTwitterWrappedFile($dataPath.'/profile.js', 'profile', [['profile' => $profile]], $dataset['malformed_files']['profile.js'] ?? null);
    }

    if (($dataset['include_tweet_headers'] ?? true) === true) {
        writeTwitterWrappedFile($dataPath.'/tweet-headers.js', 'tweet_headers', $tweetHeaders, $dataset['malformed_files']['tweet-headers.js'] ?? null);
    }

    if (($dataset['include_community_tweets'] ?? true) === true) {
        writeTwitterWrappedFile($dataPath.'/community-tweet.js', 'community_tweet', array_map(
            static fn (array $tweet): array => ['tweet' => $tweet],
            $communityTweets
        ), $dataset['malformed_files']['community-tweet.js'] ?? null);
    }

    if (($dataset['include_note_tweets'] ?? true) === true) {
        writeTwitterWrappedFile($dataPath.'/note-tweet.js', 'note_tweet', array_map(
            static fn (array $noteTweet): array => ['noteTweet' => $noteTweet],
            $noteTweets
        ), $dataset['malformed_files']['note-tweet.js'] ?? null);
    }

    if (($dataset['include_screen_name_changes'] ?? true) === true) {
        writeTwitterWrappedFile($dataPath.'/screen-name-change.js', 'screen_name_change', $screenNameChanges, $dataset['malformed_files']['screen-name-change.js'] ?? null);
    }

    if (($dataset['include_verified'] ?? true) === true) {
        writeTwitterWrappedFile(
            $dataPath.'/verified.js',
            'verified',
            [['verified' => $dataset['verified'] ?? ['verified' => true, 'verifiedAt' => '2025-01-01T00:00:00.000Z']]],
            $dataset['malformed_files']['verified.js'] ?? null
        );
    }

    if (($dataset['include_verified_organization'] ?? true) === true) {
        writeTwitterWrappedFile(
            $dataPath.'/verified-organization.js',
            'verified_organization',
            [[
                'verifiedOrganization' => $dataset['verified_organization'] ?? [
                    'verifiedOrganization' => false,
                ],
            ]],
            $dataset['malformed_files']['verified-organization.js'] ?? null
        );
    }

    File::ensureDirectoryExists($dataPath.'/tweets_media');
    File::ensureDirectoryExists($dataPath.'/community_tweet_media');
    File::ensureDirectoryExists($dataPath.'/profile_media');
    File::put($dataPath.'/tweets_media/tweet-photo-1.jpg', 'tweet-photo');
    File::put($dataPath.'/community_tweet_media/community-photo-1.jpg', 'community-photo');
    File::put($dataPath.'/profile_media/avatar.jpg', 'avatar');
    File::put($dataPath.'/profile_media/header.jpg', 'header');

    return [
        'root_path' => $root,
        'archive_path' => $archivePath,
    ];
}

/**
 * @param  array<array-key, mixed>  $payload
 */
function writeTwitterWrappedFile(string $path, string $datasetKey, array $payload, ?string $rawContent = null): void
{
    File::ensureDirectoryExists(dirname($path));

    if ($rawContent !== null) {
        File::put($path, $rawContent);

        return;
    }

    if ($datasetKey === '__THAR_CONFIG') {
        File::put($path, 'window.__THAR_CONFIG = '.json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).';');

        return;
    }

    File::put(
        $path,
        'window.YTD.'.$datasetKey.'.part0 = '.json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).';'
    );
}
