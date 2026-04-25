<?php

declare(strict_types=1);

require_once __DIR__.'/../../Support/InstagramFixtures.php';

use App\Actions\Import\ImportInstagramArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\InstagramAccount;
use App\Models\InstagramMediaRef;
use App\Models\InstagramPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/instagram'));
    File::deleteDirectory(base_path('data/runs'));
});

it('traverses instagram importer eloquent graph over imported archive data', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-model-graph', [
        'username' => 'odinn',
        'display_name' => 'Odinn Adalsteinsson',
        'email' => 'odinn@example.com',
        'phone_number' => '+45 12345678',
        'posts' => [[
            'media' => [
                [
                    'uri' => 'media/posts/202401/post-1.jpg',
                    'creation_timestamp' => 1_704_362_115,
                    'title' => 'First graph post',
                ],
                [
                    'uri' => 'media/posts/202401/post-2.jpg',
                    'creation_timestamp' => 1_704_362_116,
                    'title' => '',
                ],
            ],
        ]],
        'profile_photos' => [
            ['uri' => 'media/profile/202207/avatar.jpg', 'creation_timestamp' => 1_656_831_379],
        ],
        'stories' => [
            ['uri' => 'media/stories/202401/story-1.mp4', 'creation_timestamp' => 1_704_362_200, 'title' => 'Story title'],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    $account = InstagramAccount::query()
        ->with([
            'profileSnapshots',
            'posts.mediaRefs',
            'mediaRefs',
        ])
        ->sole();

    $profileSnapshot = $account->profileSnapshots->sole();
    $post = $account->posts->sole();

    expect($account->username)->toBe('odinn')
        ->and($account->display_name)->toBe('Odinn Adalsteinsson')
        ->and($profileSnapshot->email)->toBe('odinn@example.com')
        ->and($profileSnapshot->account->is($account))->toBeTrue()
        ->and($post->caption)->toBe('First graph post')
        ->and($post->media_count)->toBe(2)
        ->and($post->account->is($account))->toBeTrue()
        ->and($post->mediaRefs->pluck('uri')->all())->toEqualCanonicalizing([
            'media/posts/202401/post-1.jpg',
            'media/posts/202401/post-2.jpg',
        ]);

    $postMediaRef = InstagramMediaRef::query()
        ->with(['post.account', 'account'])
        ->where('media_type', 'post')
        ->orderBy('uri')
        ->firstOrFail();

    $postFromMediaRef = $postMediaRef->post ?? throw new RuntimeException('Expected post media ref to belong to a post.');
    expect($postMediaRef->post)->not->toBeNull()
        ->and($postFromMediaRef->is($post))->toBeTrue()
        ->and($postFromMediaRef->account->is($account))->toBeTrue()
        ->and($postMediaRef->account->is($account))->toBeTrue();

    $story = InstagramMediaRef::query()
        ->with('account')
        ->where('media_type', 'story')
        ->sole();

    expect($story->post)->toBeNull()
        ->and($story->title)->toBe('Story title')
        ->and($story->account->is($account))->toBeTrue();

    $profilePhoto = InstagramMediaRef::query()
        ->where('media_type', 'profile_photo')
        ->sole();

    expect($profilePhoto->post)->toBeNull();

    $postAgain = InstagramPost::query()
        ->with(['account', 'mediaRefs.account'])
        ->sole();

    expect($postAgain->account->is($account))->toBeTrue()
        ->and($postAgain->mediaRefs)->toHaveCount(2)
        ->and($postAgain->mediaRefs->every(fn (InstagramMediaRef $mediaRef): bool => $mediaRef->account->is($account)))->toBeTrue();
});
