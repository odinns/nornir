<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\TwitterImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Models\TwitterAccount;
use App\Models\TwitterMediaRef;
use App\Models\TwitterNoteTweet;
use App\Models\TwitterProfileSnapshot;
use App\Models\TwitterScreenNameChange;
use App\Models\TwitterTweet;
use App\Services\Nornir\ProvenanceWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ImportTwitterArchiveAction
{
    private const array REQUIRED_FILES = [
        'data/manifest.js',
        'data/account.js',
        'data/tweets.js',
    ];

    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly SourceObservationStore $sourceObservationStore,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): TwitterImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'twitter-import',
            import: fn (Run $run): array => DB::transaction(
                fn (): array => $this->importArchive($dispatchPayload, $run, $progress)
            ),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->importArtifactWriter->write($run, $dispatchPayload, 'twitter', 'twitter-import-summary', $summary);
            },
        );

        /** @var array{run: Run, summary: array{source_file:string, source_set_id:int, accounts:int, profile_snapshots:int, tweets:int, note_tweets:int, media_refs:int, inserted_tweets:int, reobserved_tweets:int}} $execution */
        return new TwitterImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array{source_file:string, source_set_id:int, accounts:int, profile_snapshots:int, tweets:int, note_tweets:int, media_refs:int, inserted_tweets:int, reobserved_tweets:int}
     */
    private function importArchive(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        $archivePath = $this->resolveArchivePath($dispatchPayload);
        $this->assertArchiveShape($archivePath);

        $manifest = $this->readManifest($archivePath);
        $accountEntry = $this->readOptionalDataset($archivePath, 'data/account.js', 'account')[0]['account'] ?? null;

        if (! is_array($accountEntry)) {
            throw new InvalidArgumentException('Malformed Twitter source file [data/account.js].');
        }

        $accountId = $this->stringValue($accountEntry['accountId'] ?? data_get($manifest, 'userInfo.accountId'));
        $username = $this->stringValue($accountEntry['username'] ?? data_get($manifest, 'userInfo.userName'));

        $archiveId = $this->resolveArchiveId($dispatchPayload, $manifest, $accountId, $username);

        $summary = [
            'source_file' => basename($archivePath),
            'source_set_id' => $archiveId,
            'accounts' => 0,
            'profile_snapshots' => 0,
            'tweets' => 0,
            'note_tweets' => 0,
            'media_refs' => 0,
            'inserted_tweets' => 0,
            'reobserved_tweets' => 0,
        ];

        $summary['accounts'] = $this->importAccount($archiveId, $accountEntry);

        $profile = $this->readOptionalSingletonDataset($archivePath, 'data/profile.js', 'profile', 'profile');
        $verified = $this->readOptionalSingletonDataset($archivePath, 'data/verified.js', 'verified', 'verified');
        $verifiedOrganization = $this->readOptionalSingletonDataset(
            $archivePath,
            'data/verified-organization.js',
            'verified_organization',
            'verifiedOrganization'
        );

        if (is_array($profile) || is_array($verified) || is_array($verifiedOrganization)) {
            $summary['profile_snapshots'] = $this->importProfileSnapshot(
                archivePath: $archivePath,
                archiveId: $archiveId,
                accountId: $accountId,
                profile: $profile,
                verified: $verified,
                verifiedOrganization: $verifiedOrganization,
            );
        }

        $screenNameChanges = $this->readOptionalDataset(
            $archivePath,
            'data/screen-name-change.js',
            'screen_name_change'
        );
        $this->importScreenNameChanges($archiveId, $accountId, $screenNameChanges);

        $tweets = $this->readTweetDataset($archivePath, 'data/tweets.js', 'tweets', 'tweet');
        $communityTweets = $this->readTweetDataset(
            $archivePath,
            'data/community-tweet.js',
            'community_tweet',
            'tweet',
        );
        $noteTweets = $this->readOptionalDataset($archivePath, 'data/note-tweet.js', 'note_tweet');

        $this->validateTweetHeaders($archivePath, count($tweets));

        $tweetCounts = $this->importTweets(
            run: $run,
            archivePath: $archivePath,
            archiveId: $archiveId,
            accountId: $accountId,
            tweets: $tweets,
            communityTweets: $communityTweets,
            progress: $progress,
        );

        $summary['tweets'] = $tweetCounts['tweets'];
        $summary['inserted_tweets'] = $tweetCounts['inserted_tweets'];
        $summary['reobserved_tweets'] = $tweetCounts['reobserved_tweets'];
        $summary['media_refs'] = $tweetCounts['media_refs'];
        $summary['note_tweets'] = $this->importNoteTweets($run, $archiveId, $accountId, $noteTweets);

        $summary['media_refs'] = TwitterMediaRef::query()
            ->where('account_id', $accountId)
            ->count();

        return $summary;
    }

    private function resolveArchivePath(ImporterDispatchData $dispatchPayload): string
    {
        if ($dispatchPayload->accessMode !== 'local-path') {
            throw new InvalidArgumentException('Twitter imports currently require a local-path archive directory.');
        }

        if (! File::isDirectory($dispatchPayload->sourceLocator)) {
            throw new InvalidArgumentException('Malformed Twitter source payload: archive directory was not found.');
        }

        return $dispatchPayload->sourceLocator;
    }

    private function assertArchiveShape(string $archivePath): void
    {
        foreach (self::REQUIRED_FILES as $requiredFile) {
            if (! File::exists($archivePath.'/'.$requiredFile)) {
                throw new InvalidArgumentException("Malformed Twitter source payload: missing required file [{$requiredFile}].");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function resolveArchiveId(
        ImporterDispatchData $dispatchPayload,
        array $manifest,
        ?string $accountId,
        ?string $username,
    ): int {
        $archiveGeneratedAtSource = $this->stringValue(data_get($manifest, 'archiveInfo.generationDate'));

        return $this->sourceObservationStore->upsertAndReturnId(
            table: 'twitter_archives',
            unique: [
                'archive_key' => sha1($dispatchPayload->sourceLocator),
            ],
            values: [
                'source_locator' => $dispatchPayload->sourceLocator,
                'access_mode' => $dispatchPayload->accessMode,
                'account_id' => $accountId,
                'username' => $username,
                'archive_generated_at_source' => $archiveGeneratedAtSource,
                'archive_generated_at' => $this->parseIsoTimestamp($archiveGeneratedAtSource),
                'raw_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $accountEntry
     */
    private function importAccount(int $archiveId, array $accountEntry): int
    {
        TwitterAccount::query()->updateOrCreate(
            ['twitter_archive_id' => $archiveId],
            [
                'account_id' => $this->stringValue($accountEntry['accountId'] ?? '') ?? '',
                'username' => $this->stringValue($accountEntry['username'] ?? null),
                'display_name' => $this->stringValue($accountEntry['accountDisplayName'] ?? null),
                'created_at_source' => $this->stringValue($accountEntry['createdAt'] ?? null),
                'account_created_at' => $this->parseIsoTimestamp($this->stringValue($accountEntry['createdAt'] ?? null)),
                'raw_account' => $accountEntry,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return 1;
    }

    /**
     * @param  array<string, mixed>|null  $profile
     * @param  array<string, mixed>|null  $verified
     * @param  array<string, mixed>|null  $verifiedOrganization
     */
    private function importProfileSnapshot(
        string $archivePath,
        int $archiveId,
        ?string $accountId,
        ?array $profile,
        ?array $verified,
        ?array $verifiedOrganization,
    ): int {
        $avatarMedia = $this->resolveProfileMedia($archivePath, $this->stringValue($profile['avatarMediaUrl'] ?? null));
        $headerMedia = $this->resolveProfileMedia($archivePath, $this->stringValue($profile['headerMediaUrl'] ?? null));

        TwitterProfileSnapshot::query()->updateOrCreate(
            ['twitter_archive_id' => $archiveId],
            [
                'account_id' => $accountId,
                'screen_name' => $this->stringValue($profile['screenName'] ?? null),
                'display_name' => $this->stringValue($profile['displayName'] ?? null),
                'bio' => $this->stringValue(data_get($profile, 'description.bio')),
                'location' => $this->stringValue(data_get($profile, 'description.location'))
                    ?? $this->stringValue($profile['location'] ?? null),
                'website_url' => $this->stringValue(data_get($profile, 'description.website')),
                'avatar_path' => $avatarMedia['relative_path'],
                'header_path' => $headerMedia['relative_path'],
                'is_verified' => $this->boolValue($verified['verified'] ?? null),
                'is_verified_organization' => $this->boolValue($verifiedOrganization['verifiedOrganization'] ?? null),
                'raw_profile' => [
                    'profile' => $profile,
                    'verified' => $verified,
                    'verified_organization' => $verifiedOrganization,
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        foreach ([
            ['kind' => 'avatar', 'media' => $avatarMedia],
            ['kind' => 'header', 'media' => $headerMedia],
        ] as $entry) {
            $media = $entry['media'];

            if ($media['relative_path'] === null && $media['source_url'] === null) {
                continue;
            }

            $this->upsertMediaRef(
                archiveId: $archiveId,
                accountId: $accountId,
                mediaKey: 'profile:'.$entry['kind'].':'.($media['relative_path'] ?? $media['source_url']),
                ownerType: 'profile',
                ownerId: (string) $archiveId,
                sourceSurface: 'profile',
                relativePath: $media['relative_path'],
                sourceUrl: $media['source_url'],
                mediaType: 'image',
                rawMedia: $media,
            );
        }

        return 1;
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    private function importScreenNameChanges(int $archiveId, ?string $accountId, array $changes): void
    {
        foreach ($changes as $change) {
            $payload = $change['screenNameChange'] ?? null;

            if (! is_array($payload)) {
                continue;
            }

            $changePayload = $payload['screenNameChange'] ?? $payload;

            if (! is_array($changePayload)) {
                continue;
            }

            $screenName = $this->stringValue($changePayload['changedTo'] ?? null)
                ?? $this->stringValue($payload['screenName'] ?? null);

            if ($screenName === null) {
                continue;
            }

            $changedAtSource = $this->stringValue($changePayload['changedAt'] ?? null)
                ?? $this->stringValue($payload['changedAt'] ?? null);
            $resolvedAccountId = $this->stringValue($payload['accountId'] ?? null) ?? $accountId;

            TwitterScreenNameChange::query()->updateOrCreate(
                [
                    'twitter_archive_id' => $archiveId,
                    'screen_name' => $screenName,
                    'changed_at_source' => $changedAtSource,
                ],
                [
                    'account_id' => $resolvedAccountId,
                    'changed_at' => $this->parseIsoTimestamp($changedAtSource),
                    'raw_change' => $payload,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tweets
     * @param  list<array<string, mixed>>  $communityTweets
     * @return array{tweets:int, inserted_tweets:int, reobserved_tweets:int, media_refs:int}
     */
    private function importTweets(
        Run $run,
        string $archivePath,
        int $archiveId,
        ?string $accountId,
        array $tweets,
        array $communityTweets,
        ?callable $progress,
    ): array {
        $groups = [
            ['surface' => 'tweet', 'tweets' => $tweets],
            ['surface' => 'community', 'tweets' => $communityTweets],
        ];

        $importedTweets = 0;
        $insertedTweets = 0;
        $reobservedTweets = 0;

        foreach ($groups as $group) {
            foreach ($group['tweets'] as $tweet) {
                $tweetId = $this->stringValue($tweet['id_str'] ?? null);

                if ($tweetId === null) {
                    throw new InvalidArgumentException('Malformed Twitter source file [data/tweets.js].');
                }

                $existingTweet = TwitterTweet::query()->where('tweet_id', $tweetId)->first();

                TwitterTweet::query()->updateOrCreate(
                    ['tweet_id' => $tweetId],
                    [
                        'first_seen_twitter_archive_id' => $existingTweet->first_seen_twitter_archive_id ?? $archiveId,
                        'account_id' => $accountId,
                        'source_surface' => $group['surface'],
                        'created_at_source' => $this->stringValue($tweet['created_at'] ?? null),
                        'tweeted_at' => $this->parseTwitterTimestamp($this->stringValue($tweet['created_at'] ?? null)),
                        'full_text' => $this->stringValue($tweet['full_text'] ?? null),
                        'source_client' => $this->stringValue($tweet['source'] ?? null),
                        'lang' => $this->stringValue($tweet['lang'] ?? null),
                        'conversation_id' => $this->stringValue($tweet['conversation_id_str'] ?? null),
                        'in_reply_to_tweet_id' => $this->stringValue($tweet['in_reply_to_status_id_str'] ?? null),
                        'in_reply_to_user_id' => $this->stringValue($tweet['in_reply_to_user_id_str'] ?? null),
                        'retweet_count' => $this->integerValue($tweet['retweet_count'] ?? null),
                        'reply_count' => $this->integerValue($tweet['reply_count'] ?? null),
                        'like_count' => $this->integerValue($tweet['like_count'] ?? null),
                        'quote_count' => $this->integerValue($tweet['quote_count'] ?? null),
                        'bookmark_count' => $this->integerValue($tweet['bookmark_count'] ?? null),
                        'raw_tweet' => $tweet,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $importedTweets++;
                $existingTweet === null ? $insertedTweets++ : $reobservedTweets++;

                foreach ($this->extractMedia($tweet) as $media) {
                    $this->upsertMediaRef(
                        archiveId: $archiveId,
                        accountId: $accountId,
                        mediaKey: 'tweet:'.$tweetId.':'.sha1(json_encode($media, JSON_THROW_ON_ERROR)),
                        ownerType: 'tweet',
                        ownerId: $tweetId,
                        sourceSurface: $group['surface'],
                        relativePath: $this->normalizeArchiveRelativePath($archivePath, $this->stringValue($media['media_path'] ?? null)),
                        sourceUrl: $this->stringValue($media['media_url_https'] ?? null),
                        mediaType: $this->stringValue($media['type'] ?? null),
                        rawMedia: $media,
                    );
                }

                $this->provenanceWriter->link(new WriteProvenanceLinkData(
                    runId: $run->id,
                    outputTarget: 'twitter_tweets:'.$tweetId,
                    claimKey: 'imported-tweet',
                    evidenceType: 'source-file',
                    evidenceRef: 'data/'.($group['surface'] === 'community' ? 'community-tweet.js' : 'tweets.js').'#tweet:'.$tweetId,
                ));
            }
        }

        $this->reportProgress($progress, 'tweets_imported', [
            'tweets' => $importedTweets,
        ]);

        return [
            'tweets' => $importedTweets,
            'inserted_tweets' => $insertedTweets,
            'reobserved_tweets' => $reobservedTweets,
            'media_refs' => TwitterMediaRef::query()->where('account_id', $accountId)->count(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $noteTweets
     */
    private function importNoteTweets(Run $run, int $archiveId, ?string $accountId, array $noteTweets): int
    {
        $count = 0;

        foreach ($noteTweets as $noteTweetWrapper) {
            $noteTweet = $noteTweetWrapper['noteTweet'] ?? null;

            if (! is_array($noteTweet)) {
                continue;
            }

            $noteTweetId = $this->stringValue($noteTweet['noteTweetId'] ?? null);

            if ($noteTweetId === null) {
                throw new InvalidArgumentException('Malformed Twitter source file [data/note-tweet.js].');
            }

            $existingNoteTweet = TwitterNoteTweet::query()
                ->where('note_tweet_id', $noteTweetId)
                ->first();

            TwitterNoteTweet::query()->updateOrCreate(
                ['note_tweet_id' => $noteTweetId],
                [
                    'first_seen_twitter_archive_id' => $existingNoteTweet->first_seen_twitter_archive_id ?? $archiveId,
                    'account_id' => $accountId,
                    'created_at_source' => $this->stringValue($noteTweet['createdAt'] ?? null),
                    'tweeted_at' => $this->parseIsoTimestamp($this->stringValue($noteTweet['createdAt'] ?? null)),
                    'full_text' => $this->stringValue(data_get($noteTweet, 'core.text')),
                    'raw_note_tweet' => $noteTweet,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: 'twitter_note_tweets:'.$noteTweetId,
                claimKey: 'imported-note-tweet',
                evidenceType: 'source-file',
                evidenceRef: 'data/note-tweet.js#noteTweet:'.$noteTweetId,
            ));

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $rawMedia
     */
    private function upsertMediaRef(
        int $archiveId,
        ?string $accountId,
        string $mediaKey,
        string $ownerType,
        string $ownerId,
        string $sourceSurface,
        ?string $relativePath,
        ?string $sourceUrl,
        ?string $mediaType,
        array $rawMedia,
    ): void {
        TwitterMediaRef::query()->updateOrCreate(
            ['media_key' => $mediaKey],
            [
                'twitter_archive_id' => $archiveId,
                'account_id' => $accountId,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'source_surface' => $sourceSurface,
                'relative_path' => $relativePath,
                'source_url' => $sourceUrl,
                'media_type' => $mediaType,
                'raw_media' => $rawMedia,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $archivePath): array
    {
        $raw = $this->readWrappedFile($archivePath.'/data/manifest.js');
        $prefix = 'window.__THAR_CONFIG = ';

        if (! str_starts_with($raw, $prefix)) {
            throw new InvalidArgumentException('Malformed Twitter source file [data/manifest.js].');
        }

        $payload = json_decode(rtrim(substr($raw, strlen($prefix)), ';'), true);

        if (! is_array($payload)) {
            throw new InvalidArgumentException('Malformed Twitter source file [data/manifest.js].');
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readTweetDataset(
        string $archivePath,
        string $relativePath,
        string $datasetKey,
        string $entityKey,
    ): array {
        $rows = $this->readOptionalDataset($archivePath, $relativePath, $datasetKey);
        $tweets = [];

        foreach ($rows as $row) {
            $tweet = $row[$entityKey] ?? null;

            if (! is_array($tweet)) {
                throw new InvalidArgumentException("Malformed Twitter source file [{$relativePath}].");
            }

            $tweets[] = $tweet;
        }

        return $tweets;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readOptionalDataset(
        string $archivePath,
        string $relativePath,
        string $datasetKey,
    ): array {
        $path = $archivePath.'/'.$relativePath;

        if (! File::exists($path)) {
            return [];
        }

        $raw = $this->readWrappedFile($path);
        $prefix = "window.YTD.{$datasetKey}.part0 = ";

        if (! str_starts_with($raw, $prefix)) {
            throw new InvalidArgumentException("Malformed Twitter source file [{$relativePath}].");
        }

        $payload = json_decode(rtrim(substr($raw, strlen($prefix)), ';'), true);

        if (! is_array($payload)) {
            throw new InvalidArgumentException("Malformed Twitter source file [{$relativePath}].");
        }

        return array_values(array_filter($payload, is_array(...)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readOptionalSingletonDataset(
        string $archivePath,
        string $relativePath,
        string $datasetKey,
        string $entityKey,
    ): ?array {
        $rows = $this->readOptionalDataset($archivePath, $relativePath, $datasetKey);
        $row = $rows[0][$entityKey] ?? null;

        return is_array($row) ? $row : null;
    }

    private function validateTweetHeaders(string $archivePath, int $tweetCount): void
    {
        $tweetHeaders = $this->readOptionalDataset($archivePath, 'data/tweet-headers.js', 'tweet_headers');

        if ($tweetHeaders === []) {
            return;
        }

        if (count($tweetHeaders) < $tweetCount) {
            throw new InvalidArgumentException('Malformed Twitter source file [data/tweet-headers.js].');
        }
    }

    /**
     * @param  array<string, mixed>  $tweet
     * @return list<array<string, mixed>>
     */
    private function extractMedia(array $tweet): array
    {
        $media = data_get($tweet, 'entities.media');

        if (! is_array($media)) {
            return [];
        }

        return array_values(array_filter($media, is_array(...)));
    }

    private function normalizeArchiveRelativePath(string $archivePath, ?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', $relativePath);
        $absolutePath = $archivePath.'/data/'.$normalizedPath;
        $resolvedPath = realpath($absolutePath);

        if ($resolvedPath === false || ! str_starts_with($resolvedPath, realpath($archivePath) ?: $archivePath)) {
            throw new InvalidArgumentException("Twitter media reference points outside the archive [{$relativePath}].");
        }

        if (! File::exists($resolvedPath)) {
            throw new InvalidArgumentException("Twitter media file is missing [{$relativePath}].");
        }

        return $normalizedPath;
    }

    /**
     * @return array{relative_path:?string, source_url:?string}
     */
    private function resolveProfileMedia(string $archivePath, ?string $location): array
    {
        if ($location === null) {
            return [
                'relative_path' => null,
                'source_url' => null,
            ];
        }

        if (filter_var($location, FILTER_VALIDATE_URL) !== false) {
            return [
                'relative_path' => null,
                'source_url' => $location,
            ];
        }

        return [
            'relative_path' => $this->normalizeArchiveRelativePath($archivePath, $location),
            'source_url' => null,
        ];
    }

    private function readWrappedFile(string $path): string
    {
        $content = File::get($path);

        return trim($content);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function integerValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function boolValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return null;
    }

    private function parseIsoTimestamp(?string $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return CarbonImmutable::parse($timestamp)->utc()->toDateTimeString();
    }

    private function parseTwitterTimestamp(?string $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return CarbonImmutable::parse($timestamp)->utc()->toDateTimeString();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reportProgress(?callable $progress, string $event, array $payload): void
    {
        if ($progress === null) {
            return;
        }

        $progress($event, $payload);
    }
}
