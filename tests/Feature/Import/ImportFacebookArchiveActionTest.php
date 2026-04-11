<?php

declare(strict_types=1);

use App\Actions\Import\ImportFacebookArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/facebook'));
    File::deleteDirectory(base_path('data/runs'));
});

it('imports facebook archive biography slices into canonical facebook tables', function (): void {
    $fixture = createFacebookFixtureArchive('facebook-import-primary', [
        'profile' => [
            'name' => ['full_name' => 'Odinn Test'],
            'emails' => ['emails' => [['email' => 'odinn@example.com']]],
            'current_city' => ['name' => 'Copenhagen'],
            'hometown' => ['name' => 'Akureyri'],
        ],
        'friends' => [
            ['name' => 'Alice Friend', 'timestamp' => 1_700_000_000],
        ],
        'followers' => [
            ['name' => 'Bob Follower', 'timestamp' => 1_700_000_050],
        ],
        'following' => [
            ['name' => 'Carol Followed', 'timestamp' => 1_700_000_075],
        ],
        'posts' => [
            [
                'timestamp' => 1_700_000_100,
                'title' => 'Shared a post',
                'post' => 'Hello from Facebook',
                'uri' => 'your_facebook_activity/posts/photos/post-photo.jpg',
            ],
        ],
        'comments' => [
            [
                'timestamp' => 1_700_000_120,
                'title' => 'Commented on something',
                'comment' => 'This part is actually clean',
            ],
        ],
        'reactions' => [
            [
                'timestamp' => 1_700_000_140,
                'title' => 'Reacted to a post',
                'reaction' => 'LIKE',
                'actor' => 'Odinn Test',
            ],
        ],
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'alicefriend_1234567890',
            'title' => 'Alice Friend',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [
                [
                    'sender_name' => 'Alice Friend',
                    'timestamp_ms' => 1_700_000_000_000,
                    'content' => 'Hej fra AndrÃ©',
                    'reactions' => [
                        ['reaction' => 'LIKE', 'actor' => 'Odinn Test'],
                    ],
                    'photos' => [
                        ['uri' => 'photos/pic.jpg', 'creation_timestamp' => 1_700_000_000],
                    ],
                ],
                [
                    'sender_name' => 'Odinn Test',
                    'timestamp_ms' => 1_700_000_060_000,
                    'content' => 'Replying from the importer',
                    'files' => [
                        ['uri' => 'files/notes.txt', 'creation_timestamp' => 1_700_000_060],
                    ],
                    'photos' => [
                        ['uri' => 'https://cdn.example.test/media/really-long-facebook-style-asset-path.gif?with=query&and=more', 'creation_timestamp' => 1_700_000_061],
                    ],
                ],
            ],
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'facebook',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $result = app(ImportFacebookArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('facebook_archives')->count())->toBe(1);
    expect(DB::table('facebook_people')->count())->toBe(4);
    expect(DB::table('facebook_profile_snapshots')->count())->toBe(1);
    expect(DB::table('facebook_social_edges')->count())->toBe(3);
    expect(DB::table('facebook_threads')->count())->toBe(1);
    expect(DB::table('facebook_messages')->count())->toBe(2);
    expect(DB::table('facebook_message_reactions')->count())->toBe(1);
    expect(DB::table('facebook_attachments')->count())->toBe(4);
    expect(DB::table('facebook_posts')->count())->toBe(1);
    expect(DB::table('facebook_comments')->count())->toBe(1);
    expect(DB::table('facebook_reactions')->count())->toBe(1);

    expect(DB::table('facebook_messages')->orderBy('timestamp_ms')->pluck('content')->all())->toBe([
        'Hej fra André',
        'Replying from the importer',
    ]);

    expect(DB::table('facebook_attachments')->orderBy('id')->pluck('relative_path')->all())->toEqualCanonicalizing([
        'your_facebook_activity/messages/inbox/alicefriend_1234567890/photos/pic.jpg',
        'your_facebook_activity/messages/inbox/alicefriend_1234567890/files/notes.txt',
        'your_facebook_activity/posts/photos/post-photo.jpg',
        null,
    ]);

    $remoteAttachment = DB::table('facebook_attachments')
        ->where('source_uri', 'like', 'https://cdn.example.test/%')
        ->first();

    expect($remoteAttachment)->not->toBeNull();
    expect($remoteAttachment->relative_path)->toBeNull();
});

it('reruns idempotently for the same facebook archive', function (): void {
    $fixture = createFacebookFixtureArchive('facebook-import-repeat', [
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'repeatthread_123',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [[
                'sender_name' => 'Alice Friend',
                'timestamp_ms' => 1_700_100_000_000,
                'content' => 'Same archive, same message',
            ]],
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'facebook',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importer = app(ImportFacebookArchiveAction::class);

    $firstResult = $importer($intake->dispatchPayload);
    $secondResult = $importer($intake->dispatchPayload);

    expect($secondResult->run->is($firstResult->run))->toBeTrue();
    expect(DB::table('facebook_archives')->count())->toBe(1);
    expect(DB::table('facebook_messages')->count())->toBe(1);
    expect(DB::table('facebook_message_observations')->count())->toBe(1);
    expect($secondResult->summary['inserted_messages'])->toBe(0);
    expect($secondResult->summary['reobserved_messages'])->toBe(1);
});

it('keeps older canonical messages when a newer facebook export is missing them', function (): void {
    $fullArchive = createFacebookFixtureArchive('facebook-import-full', [
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'historythread_123',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [
                [
                    'sender_name' => 'Alice Friend',
                    'timestamp_ms' => 1_700_200_000_000,
                    'content' => 'Old message that must survive',
                ],
                [
                    'sender_name' => 'Odinn Test',
                    'timestamp_ms' => 1_700_200_060_000,
                    'content' => 'Still present later',
                ],
            ],
        ]],
    ]);
    $truncatedArchive = createFacebookFixtureArchive('facebook-import-truncated', [
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'historythread_123',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [[
                'sender_name' => 'Odinn Test',
                'timestamp_ms' => 1_700_200_060_000,
                'content' => 'Still present later',
            ]],
        ]],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportFacebookArchiveAction::class);

    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'facebook',
        accessMode: 'local-path',
        sourceLocator: $fullArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fullArchive['archive_path']],
        ],
        importerOptions: [],
    ));
    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'facebook',
        accessMode: 'local-path',
        sourceLocator: $truncatedArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedArchive['archive_path']],
        ],
        importerOptions: [],
    ));

    $importer($fullIntake->dispatchPayload);
    $importer($truncatedIntake->dispatchPayload);

    expect(DB::table('facebook_archives')->count())->toBe(2);
    expect(DB::table('facebook_messages')->count())->toBe(2);
    expect(DB::table('facebook_messages')->orderBy('timestamp_ms')->pluck('content')->all())->toBe([
        'Old message that must survive',
        'Still present later',
    ]);
});
