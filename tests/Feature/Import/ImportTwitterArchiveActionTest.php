<?php

declare(strict_types=1);

use App\Actions\Import\ImportTwitterArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use App\Models\TwitterAccount;
use App\Models\TwitterArchive;
use App\Models\TwitterMediaRef;
use App\Models\TwitterNoteTweet;
use App\Models\TwitterProfileSnapshot;
use App\Models\TwitterScreenNameChange;
use App\Models\TwitterTweet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/twitter'));
    File::deleteDirectory(base_path('data/runs'));
});

it('imports twitter archive biography slices into canonical twitter tables', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-import-primary');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $result = app(ImportTwitterArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(TwitterArchive::query()->count())->toBe(1);
    expect(TwitterAccount::query()->count())->toBe(1);
    expect(TwitterProfileSnapshot::query()->count())->toBe(1);
    expect(TwitterScreenNameChange::query()->count())->toBe(1);
    expect(TwitterTweet::query()->count())->toBe(2);
    expect(TwitterNoteTweet::query()->count())->toBe(1);
    expect(TwitterMediaRef::query()->count())->toBe(4);

    expect(TwitterTweet::query()->orderBy('tweet_id')->pluck('source_surface', 'tweet_id')->all())->toBe([
        '111' => 'tweet',
        '112' => 'community',
    ]);

    expect(TwitterTweet::query()->orderBy('tweet_id')->pluck('full_text', 'tweet_id')->all())->toBe([
        '111' => 'Hello from the importer',
        '112' => 'Community post',
    ]);

    expect(TwitterMediaRef::query()->orderBy('relative_path')->pluck('relative_path')->all())->toEqualCanonicalizing([
        'community_tweet_media/community-photo-1.jpg',
        'profile_media/avatar.jpg',
        'profile_media/header.jpg',
        'tweets_media/tweet-photo-1.jpg',
    ]);
});

it('exposes first-seen tweet relations from imported twitter archives', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-import-model-relations');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    app(ImportTwitterArchiveAction::class)($intake->dispatchPayload);

    $archive = TwitterArchive::query()
        ->with(['account', 'profileSnapshot', 'screenNameChanges', 'mediaRefs', 'tweets', 'noteTweets'])
        ->sole();

    expect($archive->account?->account_id)->toBe('123456')
        ->and($archive->profileSnapshot?->screen_name)->toBe('odinn')
        ->and($archive->screenNameChanges->pluck('screen_name')->all())->toBe(['oldodinn'])
        ->and($archive->mediaRefs->pluck('relative_path')->filter()->values()->all())->toEqualCanonicalizing([
            'community_tweet_media/community-photo-1.jpg',
            'profile_media/avatar.jpg',
            'profile_media/header.jpg',
            'tweets_media/tweet-photo-1.jpg',
        ])
        ->and($archive->tweets->pluck('tweet_id')->all())->toEqualCanonicalizing(['111', '112'])
        ->and($archive->noteTweets->pluck('note_tweet_id')->all())->toBe(['note-1']);
});

it('reruns idempotently for the same twitter archive', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-import-repeat');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importer = app(ImportTwitterArchiveAction::class);

    $firstResult = $importer($intake->dispatchPayload);
    $secondResult = $importer($intake->dispatchPayload);

    expect($secondResult->run->is($firstResult->run))->toBeTrue();
    expect(TwitterArchive::query()->count())->toBe(1);
    expect(TwitterTweet::query()->count())->toBe(2);
    expect(TwitterNoteTweet::query()->count())->toBe(1);
    expect($secondResult->summary['inserted_tweets'])->toBe(0);
    expect($secondResult->summary['reobserved_tweets'])->toBe(2);
});

it('keeps older canonical twitter rows when a newer archive is missing them', function (): void {
    $fullArchive = createTwitterFixtureArchive('twitter-import-full');
    $truncatedArchive = createTwitterFixtureArchive('twitter-import-truncated', [
        'include_community_tweets' => false,
        'include_note_tweets' => false,
        'include_screen_name_changes' => false,
        'tweets' => [[
            'id_str' => '111',
            'created_at' => 'Tue Feb 17 06:30:43 +0000 2026',
            'full_text' => 'Hello from the importer',
            'source' => 'Twitter Web App',
            'lang' => 'en',
            'conversation_id_str' => '111',
        ]],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportTwitterArchiveAction::class);

    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fullArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fullArchive['archive_path']],
        ],
        importerOptions: [],
    ));
    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $truncatedArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedArchive['archive_path']],
        ],
        importerOptions: [],
    ));

    $importer($fullIntake->dispatchPayload);
    $importer($truncatedIntake->dispatchPayload);

    expect(TwitterArchive::query()->count())->toBe(2);
    expect(TwitterTweet::query()->count())->toBe(2);
    expect(TwitterNoteTweet::query()->count())->toBe(1);
});

it('fails clearly when a supported twitter file has the wrong wrapper shape', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-import-malformed', [
        'malformed_files' => [
            'tweets.js' => 'window.YTD.wrong_dataset.part0 = [];',
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    expect(fn () => app(ImportTwitterArchiveAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'Malformed Twitter source file [data/tweets.js].');
});

it('accepts remote profile media urls without treating them as archive traversal', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-import-remote-profile-media', [
        'profile' => [
            'screenName' => 'odinn',
            'displayName' => 'Odinn Test',
            'description' => [
                'bio' => 'Remote media case',
                'website' => 'https://example.test',
            ],
            'location' => 'Copenhagen',
            'avatarMediaUrl' => 'https://pbs.twimg.com/profile_images/example/avatar.jpg',
            'headerMediaUrl' => 'https://pbs.twimg.com/profile_banners/example/header',
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $result = app(ImportTwitterArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    $profileSnapshot = TwitterProfileSnapshot::query()->sole();

    expect($profileSnapshot->avatar_path)->toBeNull();
    expect($profileSnapshot->header_path)->toBeNull();
    expect(TwitterMediaRef::query()
        ->where('owner_type', 'profile')
        ->whereNotNull('source_url')
        ->count())->toBe(2);
});

it('imports profile location from the nested description payload used by real archives', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-import-nested-location', [
        'profile' => [
            'description' => [
                'bio' => 'Nested location case',
                'website' => 'https://example.test',
                'location' => 'Taastrup, Denmark',
            ],
            'avatarMediaUrl' => 'https://pbs.twimg.com/profile_images/example/avatar.jpg',
            'headerMediaUrl' => 'https://pbs.twimg.com/profile_banners/example/header',
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    app(ImportTwitterArchiveAction::class)($intake->dispatchPayload);

    expect(TwitterProfileSnapshot::query()->sole()->location)->toBe('Taastrup, Denmark');
});

it('imports screen name changes from the nested real-archive shape', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-import-screen-name-change', [
        'screen_name_changes' => [[
            'screenNameChange' => [
                'accountId' => '123456',
                'screenNameChange' => [
                    'changedAt' => '2023-10-01T04:12:34.000Z',
                    'changedFrom' => 'odinnsorensen',
                    'changedTo' => 'odinns_art',
                ],
            ],
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    app(ImportTwitterArchiveAction::class)($intake->dispatchPayload);

    $change = TwitterScreenNameChange::query()->sole();

    expect($change->account_id)->toBe('123456')
        ->and($change->screen_name)->toBe('odinns_art')
        ->and($change->changed_at_source)->toBe('2023-10-01T04:12:34.000Z');
});
