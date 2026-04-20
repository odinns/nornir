<?php

declare(strict_types=1);

use App\Actions\Import\BuildInstagramSourcePageHandoffAction;
use App\Actions\Import\ImportInstagramArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/instagram'));
    File::deleteDirectory(base_path('data/runs'));
});

it('builds a compile-facing handoff from canonical instagram rows', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-handoff-basic', [
        'username' => 'handoff_user',
        'posts' => [
            [
                'media' => [[
                    'uri' => 'media/posts/202401/post-a.jpg',
                    'creation_timestamp' => 1_704_362_115,
                    'title' => 'Post A',
                ]],
            ],
            [
                'media' => [[
                    'uri' => 'media/posts/202401/post-b.jpg',
                    'creation_timestamp' => 1_704_462_115,
                    'title' => 'Post B',
                ]],
            ],
        ],
        'profile_photos' => [
            ['uri' => 'media/profile/202207/profile.jpg', 'creation_timestamp' => 1_656_831_379],
        ],
        'stories' => [
            ['uri' => 'media/stories/202401/story-1.mp4', 'creation_timestamp' => 1_704_362_200, 'title' => ''],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: ['archive_root' => $fixture['archive_path']],
        importerOptions: [],
    ));

    $importResult = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);
    $handoff = app(BuildInstagramSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->sourceType)->toBe('instagram');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($handoff->canonicalScope['username'])->toBe('handoff_user');
    expect($handoff->canonicalScope['tables'])->toBe([
        'instagram_accounts',
        'instagram_profile_snapshots',
        'instagram_posts',
        'instagram_media_refs',
    ]);
    expect($handoff->canonicalScope['row_counts'])->toBe([
        'posts' => 2,
        'media_refs' => 4,
    ]);
});

it('builds instagram handoff without rereading raw archive files', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-handoff-canonical-only', [
        'username' => 'canonical_only_user',
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: ['archive_root' => $fixture['archive_path']],
        importerOptions: [],
    ));

    $importResult = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    File::delete($fixture['archive_path'].'/personal_information/personal_information/personal_information.json');

    $handoff = app(BuildInstagramSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->canonicalScope['username'])->toBe('canonical_only_user');
    expect($handoff->canonicalScope['row_counts']['posts'])->toBe(1);
});

it('rejects runs that are not successful instagram imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'import',
        'operation' => 'apple-messages-import',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['source_locator' => '/tmp/chat.db', 'scope_snapshot' => []],
        'idempotency_key' => 'apple-messages-import:irrelevant',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildInstagramSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful Instagram import.');
});
