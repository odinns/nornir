<?php

declare(strict_types=1);

use App\Models\WaybackCapture;
use App\Models\WaybackScope;
use Carbon\CarbonImmutable;

it('maps wayback importer tables through explicit eloquent model contracts', function (): void {
    $scope = new WaybackScope([
        'filter_policy' => ['statuscode' => 200],
    ]);
    $capture = new WaybackCapture([
        'captured_at' => '2003-01-02 03:04:05',
        'cdx_fields' => ['digest' => 'digest-one'],
        'retrieval_metadata' => ['source' => 'internet-archive-wayback'],
        'raw_cdx_json' => ['timestamp' => '20030102030405'],
    ]);

    expect($scope->getTable())->toBe('wayback_scopes');
    expect($scope->filter_policy)->toBeArray();
    expect($scope->captures()->getForeignKeyName())->toBe('wayback_scope_id');

    expect($capture->getTable())->toBe('wayback_captures');
    expect($capture->captured_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($capture->cdx_fields)->toBeArray();
    expect($capture->retrieval_metadata)->toBeArray();
    expect($capture->raw_cdx_json)->toBeArray();
    expect($capture->scope()->getForeignKeyName())->toBe('wayback_scope_id');
});
