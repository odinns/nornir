<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/media-collection'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports media-collection rows from the cli with useful default output', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-console', [
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

    $this->artisan('import:media-collection', [
        'source' => $fixture['env_path'],
    ])
        ->expectsOutputToContain('Recording intake for media-collection source')
        ->expectsOutputToContain('Importing media collection')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(1);
    expect(DB::table('media_files')->count())->toBe(2);
});

it('restricts media-collection rows from the cli by path prefix', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-console-path-prefix', [
        [
            'volume_label' => 'LIMA-2',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-2/Pictures/2022/2022-06-01 Test',
                    'files' => [
                        ['basename' => 'keep.jpg', 'normalized_file_type' => 'image'],
                    ],
                ],
                [
                    'full_path' => '/Volumes/LIMA-2/Pictures Archive/2022/2022-06-01 Test',
                    'files' => [
                        ['basename' => 'skip.jpg', 'normalized_file_type' => 'image'],
                    ],
                ],
            ],
        ],
    ]);

    $this->artisan('import:media-collection', [
        'source' => $fixture['env_path'],
        '--path-prefix' => '/Volumes/LIMA-2/Pictures',
    ])
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(DB::table('media_files')->count())->toBe(1);
    expect(DB::table('media_files')->value('basename'))->toBe('keep.jpg');
});
