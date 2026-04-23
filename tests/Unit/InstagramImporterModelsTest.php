<?php

declare(strict_types=1);

use App\Models\InstagramAccount;
use App\Models\InstagramMediaRef;
use App\Models\InstagramPost;
use App\Models\InstagramProfileSnapshot;
use Carbon\CarbonImmutable;

it('maps instagram importer tables through explicit eloquent model contracts', function (): void {
    $account = new InstagramAccount;
    $profileSnapshot = new InstagramProfileSnapshot([
        'snapshotted_at' => '2026-04-24 08:30:00',
        'raw_payload' => ['profile_user' => ['username' => 'odinn']],
    ]);
    $post = new InstagramPost([
        'post_timestamp' => '1704362115',
        'media_count' => '3',
        'raw_payload' => ['media' => [['uri' => 'media/posts/202401/post.jpg']]],
    ]);
    $mediaRef = new InstagramMediaRef([
        'creation_timestamp' => '1704362115',
    ]);

    expect($account->getTable())->toBe('instagram_accounts')
        ->and($account->profileSnapshots()->getForeignKeyName())->toBe('instagram_account_id')
        ->and($account->posts()->getForeignKeyName())->toBe('instagram_account_id')
        ->and($account->mediaRefs()->getForeignKeyName())->toBe('instagram_account_id');

    expect($profileSnapshot->getTable())->toBe('instagram_profile_snapshots')
        ->and($profileSnapshot->snapshotted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($profileSnapshot->raw_payload)->toBeArray()
        ->and($profileSnapshot->account()->getForeignKeyName())->toBe('instagram_account_id');

    expect($post->getTable())->toBe('instagram_posts')
        ->and($post->post_timestamp)->toBe(1704362115)
        ->and($post->media_count)->toBe(3)
        ->and($post->raw_payload)->toBeArray()
        ->and($post->account()->getForeignKeyName())->toBe('instagram_account_id')
        ->and($post->mediaRefs()->getForeignKeyName())->toBe('instagram_post_id');

    expect($mediaRef->getTable())->toBe('instagram_media_refs')
        ->and($mediaRef->creation_timestamp)->toBe(1704362115)
        ->and($mediaRef->account()->getForeignKeyName())->toBe('instagram_account_id')
        ->and($mediaRef->post()->getForeignKeyName())->toBe('instagram_post_id');
});
