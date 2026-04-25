<?php

declare(strict_types=1);

use App\Actions\Import\BuildMediaSourcePageHandoffAction;
use App\Actions\Import\ImportMediaCollectionAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/media-collection'));
    File::deleteDirectory(base_path('data/runs'));
});

it('builds a compile-facing handoff from canonical media_files rows', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-handoff-basic', [
        [
            'volume_label' => 'LIMA-1',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-1/Pictures/2022/2022-06-01 Test',
                    'files' => [
                        ['basename' => 'a.jpg', 'normalized_file_type' => 'image'],
                        ['basename' => 'b.mp4', 'normalized_file_type' => 'video'],
                    ],
                ],
            ],
        ],
        [
            'volume_label' => 'LIMA-2',
            'directories' => [
                [
                    'full_path' => '/Volumes/LIMA-2/Pictures/2023/2023-01-01 NewYear',
                    'files' => [
                        ['basename' => 'c.jpg', 'normalized_file_type' => 'image'],
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

    $importResult = app(ImportMediaCollectionAction::class)($intake->dispatchPayload);
    $handoff = app(BuildMediaSourcePageHandoffAction::class)($importResult->run->id);
    /** @var array{row_counts:array{media_files:int}, tables:list<string>, volumes:list<string>, volume_filter:?string} $scope */
    $scope = $handoff->canonicalScope;

    expect($handoff->sourceType)->toBe('media-collection');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($scope['row_counts']['media_files'])->toBe(3);
    expect($scope['tables'])->toBe(['media_files']);
    expect($scope['volumes'])->toEqualCanonicalizing(['LIMA-1', 'LIMA-2']);
    expect($scope['volume_filter'])->toBeNull();
});

it('respects volume scope — counts and volumes list are filtered to the run scope', function (): void {
    $fixture = createMoniqueFixtureDatabase('media-handoff-scoped', [
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
                    'files' => [
                        ['basename' => 'b.jpg', 'normalized_file_type' => 'image'],
                        ['basename' => 'c.jpg', 'normalized_file_type' => 'image'],
                    ],
                ],
            ],
        ],
    ]);

    // Import ALL volumes first so LIMA-1 rows exist in media_files
    $allIntake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => null],
        importerOptions: ['volume' => null, 'dry_run' => false],
    ));
    app(ImportMediaCollectionAction::class)($allIntake->dispatchPayload);

    // Then run scoped to LIMA-2 only
    $scopedIntake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'media-collection',
        accessMode: 'db-connection',
        sourceLocator: $fixture['connection_name'],
        scopeSnapshot: ['source_dsn' => $fixture['connection_name'], 'volume' => 'LIMA-2'],
        importerOptions: ['volume' => 'LIMA-2', 'dry_run' => false],
    ));
    $importResult = app(ImportMediaCollectionAction::class)($scopedIntake->dispatchPayload);

    $handoff = app(BuildMediaSourcePageHandoffAction::class)($importResult->run->id);
    /** @var array{row_counts:array{media_files:int}, volumes:list<string>, volume_filter:?string} $scope */
    $scope = $handoff->canonicalScope;

    expect($scope['row_counts']['media_files'])->toBe(2);
    expect($scope['volumes'])->toBe(['LIMA-2']);
    expect($scope['volume_filter'])->toBe('LIMA-2');
});

it('rejects runs that are not successful media-collection imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'import',
        'operation' => 'apple-messages-import',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['source_locator' => '/tmp/chat.db', 'scope_snapshot' => []],
        'idempotency_key' => 'apple-messages-import:irrelevant',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildMediaSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful media-collection import.');
});
