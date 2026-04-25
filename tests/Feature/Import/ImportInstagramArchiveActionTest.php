<?php

declare(strict_types=1);

use App\Actions\Import\ImportInstagramArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/instagram'));
    File::deleteDirectory(base_path('data/runs'));
});

it('fails when archive root does not exist', function (): void {
    // Create dir for intake validation, then remove it before import runs
    $root = storage_path('framework/testing/instagram-gone-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($root);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $root,
        scopeSnapshot: [],
        importerOptions: [],
    ));

    File::deleteDirectory($root);

    expect(fn () => app(ImportInstagramArchiveAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'does not exist');

    expect(DB::table('runs')->where('status', Run::STATUS_FAILED)->count())->toBe(1);
});

it('fails when required archive files are missing', function (): void {
    $root = storage_path('framework/testing/instagram-missing-files-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($root);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $root,
        scopeSnapshot: [],
        importerOptions: [],
    ));

    expect(fn () => app(ImportInstagramArchiveAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'missing');

    expect(DB::table('runs')->where('status', Run::STATUS_FAILED)->count())->toBe(1);
});

it('imports posts with captions and timestamps', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-posts', [
        'posts' => [
            [
                'media' => [[
                    'uri' => 'media/posts/202401/photo-abc.jpg',
                    'creation_timestamp' => 1_704_362_115,
                    'title' => 'First post caption',
                ]],
            ],
            [
                'media' => [[
                    'uri' => 'media/posts/202402/photo-def.jpg',
                    'creation_timestamp' => 1_706_000_000,
                    'title' => '',
                ]],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: ['archive_root' => $fixture['archive_path']],
        importerOptions: [],
    ));

    $result = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('instagram_posts')->count())->toBe(2);
    expect($result->summary['posts'])->toBe(2);
    expect($result->summary['inserted_posts'])->toBe(2);
    expect($result->summary['reobserved_posts'])->toBe(0);

    $first = DB::table('instagram_posts')->orderBy('post_timestamp')->first();
    expect($first->caption)->toBe('First post caption');
    expect((int) $first->post_timestamp)->toBe(1_704_362_115);

    $second = DB::table('instagram_posts')->orderBy('post_timestamp', 'desc')->first();
    expect($second->caption)->toBeNull();
});

it('imports media refs for posts', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-media-refs', [
        'posts' => [
            [
                'media' => [
                    ['uri' => 'media/posts/202401/a.jpg', 'creation_timestamp' => 1_704_362_115, 'title' => 'caption'],
                    ['uri' => 'media/posts/202401/b.jpg', 'creation_timestamp' => 1_704_362_115, 'title' => ''],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    $result = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('instagram_posts')->count())->toBe(1);
    expect(DB::table('instagram_posts')->first()->media_count)->toBe(2);

    $postMediaRefs = DB::table('instagram_media_refs')
        ->where('media_type', 'post')
        ->orderBy('uri')
        ->get();
    expect($postMediaRefs)->toHaveCount(2);
    expect($postMediaRefs[0]->uri)->toBe('media/posts/202401/a.jpg');
    expect($postMediaRefs[0]->title)->toBe('caption');
    expect($postMediaRefs[1]->uri)->toBe('media/posts/202401/b.jpg');
    expect($postMediaRefs[1]->title)->toBeNull();
});

it('imports profile photos', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-profile-photos', [
        'profile_photos' => [
            ['uri' => 'media/profile/202207/avatar.jpg', 'creation_timestamp' => 1_656_831_379],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    $result = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect($result->summary['profile_photos'])->toBe(1);

    $photo = DB::table('instagram_media_refs')->where('media_type', 'profile_photo')->first();
    expect($photo->uri)->toBe('media/profile/202207/avatar.jpg');
    expect($photo->instagram_post_id)->toBeNull();
});

it('imports stories when present', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-stories', [
        'stories' => [
            ['uri' => 'media/stories/202401/story-1.mp4', 'creation_timestamp' => 1_704_362_200, 'title' => ''],
            ['uri' => 'media/stories/202401/story-2.mp4', 'creation_timestamp' => 1_704_362_300, 'title' => 'Story caption'],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    $result = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect($result->summary['stories'])->toBe(2);
    expect($result->summary['stories_skipped'])->toBeFalse();

    expect(DB::table('instagram_media_refs')->where('media_type', 'story')->count())->toBe(2);
});

it('skips stories cleanly when stories json is absent', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-no-stories', [
        'stories' => false,
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    $result = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect($result->summary['stories'])->toBe(0);
    expect($result->summary['stories_skipped'])->toBeTrue();
    expect(DB::table('instagram_media_refs')->where('media_type', 'story')->count())->toBe(0);
});

it('imports instagram archive idempotently', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-idempotent', [
        'username' => 'idempotentuser',
        'posts' => [
            [
                'media' => [[
                    'uri' => 'media/posts/202401/idem.jpg',
                    'creation_timestamp' => 1_704_362_115,
                    'title' => 'Same post',
                ]],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: ['archive_root' => $fixture['archive_path']],
        importerOptions: [],
    ));

    $importer = app(ImportInstagramArchiveAction::class);

    $first = $importer($intake->dispatchPayload);
    $second = $importer($intake->dispatchPayload);

    expect($second->run->is($first->run))->toBeTrue();
    expect(DB::table('instagram_accounts')->count())->toBe(1);
    expect(DB::table('instagram_posts')->count())->toBe(1);
    expect($second->summary['inserted_posts'])->toBe(0);
    expect($second->summary['reobserved_posts'])->toBe(1);
});

it('counts reobserved vs inserted correctly on incremental rerun', function (): void {
    $smallFixture = createInstagramFixtureArchive('instagram-incremental-small', [
        'posts' => [
            [
                'media' => [[
                    'uri' => 'media/posts/202401/existing.jpg',
                    'creation_timestamp' => 1_704_362_115,
                    'title' => 'Existing post',
                ]],
            ],
        ],
    ]);

    $largeFixture = createInstagramFixtureArchive('instagram-incremental-large', [
        'posts' => [
            [
                'media' => [[
                    'uri' => 'media/posts/202401/existing.jpg',
                    'creation_timestamp' => 1_704_362_115,
                    'title' => 'Existing post',
                ]],
            ],
            [
                'media' => [[
                    'uri' => 'media/posts/202402/new.jpg',
                    'creation_timestamp' => 1_706_000_000,
                    'title' => 'New post',
                ]],
            ],
        ],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportInstagramArchiveAction::class);

    $smallIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $smallFixture['archive_path'],
        scopeSnapshot: ['archive_root' => $smallFixture['archive_path']],
        importerOptions: [],
    ));
    $largeIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $largeFixture['archive_path'],
        scopeSnapshot: ['archive_root' => $largeFixture['archive_path']],
        importerOptions: [],
    ));

    $importer($smallIntake->dispatchPayload);
    $result = $importer($largeIntake->dispatchPayload);

    expect(DB::table('instagram_posts')->count())->toBe(2);
    expect($result->summary['inserted_posts'])->toBe(1);
    expect($result->summary['reobserved_posts'])->toBe(1);
});

it('handles carousel posts with multiple media items', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-carousel', [
        'posts' => [
            [
                'media' => [
                    ['uri' => 'media/posts/202401/c1.jpg', 'creation_timestamp' => 1_704_362_115, 'title' => 'carousel caption'],
                    ['uri' => 'media/posts/202401/c2.jpg', 'creation_timestamp' => 1_704_362_115, 'title' => ''],
                    ['uri' => 'media/posts/202401/c3.jpg', 'creation_timestamp' => 1_704_362_115, 'title' => ''],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    $result = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('instagram_posts')->count())->toBe(1);
    expect(DB::table('instagram_posts')->first()->media_count)->toBe(3);
    expect(DB::table('instagram_media_refs')->where('media_type', 'post')->count())->toBe(3);
    // All media refs point to the same post
    expect(DB::table('instagram_media_refs')->whereNotNull('instagram_post_id')->count())->toBe(3);
});

it('imports account from personal information', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-account-import', [
        'username' => 'odinnadalsteinsson',
        'display_name' => 'Odinn Adalsteinsson',
        'email' => 'odinn@example.com',
        'phone_number' => '+4512345678',
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: ['archive_root' => $fixture['archive_path']],
        importerOptions: [],
    ));

    $result = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('instagram_accounts')->count())->toBe(1);

    $account = DB::table('instagram_accounts')->first();
    expect($account->username)->toBe('odinnadalsteinsson');
    expect($account->display_name)->toBe('Odinn Adalsteinsson');
    expect($account->email)->toBe('odinn@example.com');
    expect($account->phone_number)->toBe('+4512345678');
    expect($account->access_mode)->toBe('local-path');

    expect(DB::table('instagram_profile_snapshots')->count())->toBe(1);
    $snapshot = DB::table('instagram_profile_snapshots')->first();
    expect($snapshot->instagram_account_id)->toBe($account->id);
    expect($snapshot->username)->toBe('odinnadalsteinsson');
    expect($snapshot->display_name)->toBe('Odinn Adalsteinsson');
});
