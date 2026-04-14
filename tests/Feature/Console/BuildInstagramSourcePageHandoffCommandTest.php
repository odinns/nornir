<?php

declare(strict_types=1);

use App\Actions\Import\ImportInstagramArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/instagram'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('builds an instagram source-page handoff from the cli', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-handoff-cli', [
        'username' => 'handoff_cli',
        'posts' => [
            [
                'media' => [[
                    'uri' => 'media/posts/202401/post-1.jpg',
                    'creation_timestamp' => 1_704_362_115,
                    'title' => 'CLI handoff post',
                ]],
            ],
            [
                'media' => [[
                    'uri' => 'media/posts/202401/post-2.jpg',
                    'creation_timestamp' => 1_704_462_115,
                    'title' => '',
                ]],
            ],
        ],
        'stories' => false,
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'instagram',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: ['archive_root' => $fixture['archive_path']],
        importerOptions: [],
    ));

    $importResult = app(ImportInstagramArchiveAction::class)($intake->dispatchPayload);

    $this->artisan('handoff:instagram-source-pages', [
        '--run-id' => $importResult->run->id,
    ])
        ->expectsOutputToContain('Building Instagram source-page handoff')
        ->expectsOutputToContain('Account: handoff_cli')
        ->expectsOutputToContain('Post count: 2')
        ->expectsOutputToContain('Media ref count: 3')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});
