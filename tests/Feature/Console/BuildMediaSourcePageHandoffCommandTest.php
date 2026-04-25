<?php

declare(strict_types=1);

use App\Actions\Import\ImportMediaCollectionAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/media-collection'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('builds a media-collection source-page handoff from the cli', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-handoff-cli', [
        [
            'volume_label' => 'LIMA-2',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-2/Pictures/2022/2022-06-01 Test',
                    'files' => [
                        ['basename' => 'photo.jpg', 'normalized_file_type' => 'image'],
                        ['basename' => 'clip.mp4', 'normalized_file_type' => 'video'],
                    ],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => 'LIMA-2'],
        importerOptions: ['volume' => 'LIMA-2', 'dry_run' => false],
    ));

    $importResult = app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    artisanCommand($this, 'handoff:media-source-pages', [
        '--run-id' => $importResult->run->id,
    ])
        ->expectsOutputToContain('Building media-collection source-page handoff')
        ->expectsOutputToContain('Volume filter: LIMA-2')
        ->expectsOutputToContain('Media file count: 2')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});
