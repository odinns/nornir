<?php

declare(strict_types=1);

use App\Actions\Import\ImportMediaCollectionAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\MediaFile;
use App\Models\ProvenanceLink;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/media-collection'));
    File::deleteDirectory(base_path('data/runs'));
});

// ---------------------------------------------------------------------------
// Tracer bullet
// ---------------------------------------------------------------------------

it('imports a single image from monique into media_files', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-basic', [
        [
            'volume_label' => 'LIMA-1',
            'mount_path_last_seen' => '/Volumes/LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-06-15 Birthday',
                    'files' => [
                        [
                            'basename' => 'IMG_0001.jpg',
                            'extension' => 'jpg',
                            'normalized_file_type' => 'image',
                            'size_bytes' => 3_456_789,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    $result = app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('media_files')->count())->toBe(1);

    $row = MediaFile::firstOrFail();
    expect($row->volume_label)->toBe('LIMA-1');
    expect($row->basename)->toBe('IMG_0001.jpg');
    expect($row->normalized_file_type)->toBe('image');
    expect($result->summary['files_imported'])->toBe(1);
    expect($result->summary['files_reobserved'])->toBe(0);
});

// ---------------------------------------------------------------------------
// Event date parsing
// ---------------------------------------------------------------------------

it('parses event_date from a full date prefix in directory name', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-date-full', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-06-15 Birthday',
                    'files' => [['basename' => 'photo.jpg', 'normalized_file_type' => 'image']],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    $row = MediaFile::firstOrFail();
    expect($row->event_label)->toBe('2022-06-15 Birthday');
    expect($row->event_date)->toBe('2022-06-15');
});

it('parses event_date to first of month when only year-month present', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-date-month', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2019/2019-07 Summer',
                    'files' => [['basename' => 'photo.jpg', 'normalized_file_type' => 'image']],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    $row = MediaFile::firstOrFail();
    expect($row->event_date)->toBe('2019-07-01');
});

it('parses event_date to jan 1 when only year present', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-date-year', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2015/2015 Misc',
                    'files' => [['basename' => 'photo.jpg', 'normalized_file_type' => 'image']],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    $row = MediaFile::firstOrFail();
    expect($row->event_date)->toBe('2015-01-01');
});

it('uses year dir as event label when no event subdir exists', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-date-year-dir', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2018',
                    'files' => [['basename' => 'photo.jpg', 'normalized_file_type' => 'image']],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    $row = MediaFile::firstOrFail();
    expect($row->event_label)->toBe('2018');
    expect($row->event_date)->toBe('2018-01-01');
});

it('sets event_date null when directory name has no date prefix', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-date-null', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2015/Random Event Name',
                    'files' => [['basename' => 'photo.jpg', 'normalized_file_type' => 'image']],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    $row = MediaFile::firstOrFail();
    expect($row->event_date)->toBeNull();
});

// ---------------------------------------------------------------------------
// Idempotency
// ---------------------------------------------------------------------------

it('is idempotent — rerun produces no new inserts', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-idempotent', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-01-01 NewYear',
                    'files' => [
                        ['basename' => 'a.jpg', 'normalized_file_type' => 'image'],
                        ['basename' => 'b.mp4', 'normalized_file_type' => 'video'],
                    ],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);
    $secondResult = app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    expect(DB::table('media_files')->count())->toBe(2);
    expect($secondResult->summary['files_imported'])->toBe(0);
    expect($secondResult->summary['files_reobserved'])->toBe(2);
});

// ---------------------------------------------------------------------------
// Scope filters
// ---------------------------------------------------------------------------

it('excludes resource fork files with ._ prefix', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-resource-forks', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-01-01 Test',
                    'files' => [
                        ['basename' => 'photo.jpg', 'normalized_file_type' => 'image'],
                        ['basename' => '._photo.jpg', 'normalized_file_type' => 'image'],
                    ],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    expect(DB::table('media_files')->count())->toBe(1);
    expect(MediaFile::firstOrFail()->basename)->toBe('photo.jpg');
});

it('excludes non-pictures directories', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-non-pictures', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Documents/Reports',
                    'files' => [
                        ['basename' => 'report.jpg', 'normalized_file_type' => 'image'],
                    ],
                ],
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-01-01 Test',
                    'files' => [
                        ['basename' => 'photo.jpg', 'normalized_file_type' => 'image'],
                    ],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    expect(DB::table('media_files')->count())->toBe(1);
    expect(MediaFile::firstOrFail()->basename)->toBe('photo.jpg');
});

it('restricts import to a single volume when --volume is given', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-volume-filter', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-01-01 A',
                    'files' => [['basename' => 'a.jpg', 'normalized_file_type' => 'image']],
                ],
            ],
        ],
        [
            'volume_label' => 'LIMA-2',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-2/Pictures/2022/2022-01-01 B',
                    'files' => [['basename' => 'b.jpg', 'normalized_file_type' => 'image']],
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

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    expect(DB::table('media_files')->count())->toBe(1);
    $file = MediaFile::firstOrFail();
    expect($file->basename)->toBe('b.jpg');
    expect($file->volume_label)->toBe('LIMA-2');
});

it('restricts import to a path prefix when one is given', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-path-prefix', [
        [
            'volume_label' => 'LIMA-2',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-2/Pictures/2022/2022-01-01 A',
                    'files' => [['basename' => 'keep.jpg', 'normalized_file_type' => 'image']],
                ],
                [
                    'full_path' => '/Volumes/LIMA-2/Pictures Archive/2022/2022-01-01 B',
                    'files' => [['basename' => 'skip.jpg', 'normalized_file_type' => 'image']],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null, 'path_prefix' => '/Volumes/LIMA-2/Pictures'],
        importerOptions: ['volume' => null, 'path_prefix' => '/Volumes/LIMA-2/Pictures', 'dry_run' => false],
    ));

    $result = app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    expect(DB::table('media_files')->count())->toBe(1);
    expect(DB::table('media_files')->value('basename'))->toBe('keep.jpg');
    expect($result->summary['path_prefix'])->toBe('/Volumes/LIMA-2/Pictures');
});

// ---------------------------------------------------------------------------
// Dry run
// ---------------------------------------------------------------------------

it('reports counts without writing when --dry-run is set', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-dry-run', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-01-01 A',
                    'files' => [['basename' => 'a.jpg', 'normalized_file_type' => 'image']],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => true],
    ));

    $result = app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    expect(DB::table('media_files')->count())->toBe(0);
    expect($result->summary['files_inspected'])->toBe(1);
    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
});

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------

it('fails gracefully when monique connection is unreachable', function (): void {
    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: 'nonexistent_connection',
        scopeSnapshot: ['source_dsn' => 'nonexistent_connection', 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    expect(fn () => app(ImportMediaCollectionAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class);

    expect(DB::table('runs')->where('status', Run::STATUS_FAILED)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Provenance
// ---------------------------------------------------------------------------

it('writes a provenance link per imported file', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-provenance', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-06-01 Test',
                    'files' => [
                        ['basename' => 'a.jpg', 'normalized_file_type' => 'image'],
                        ['basename' => 'b.jpg', 'normalized_file_type' => 'image'],
                    ],
                ],
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));

    app(ImportMediaCollectionAction::class)($intake->dispatchPayload);

    expect(ProvenanceLink::query()->where('claim_key', 'imported-media-file')->count())->toBe(2);

    $link = ProvenanceLink::query()->where('claim_key', 'imported-media-file')->firstOrFail();
    expect($link->evidence_type)->toBe('db-row');
    expect($link->evidence_ref)->toStartWith('monique#files:');
});
