<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/instagram'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports instagram archives from the cli with useful default output', function (): void {
    $fixture = createInstagramFixtureArchive('instagram-console');

    $this->artisan('import:instagram', [
        'source' => $fixture['archive_path'],
    ])
        ->expectsOutputToContain('Recording intake for Instagram source')
        ->expectsOutputToContain('Importing Instagram archive')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(1);
    expect(DB::table('instagram_posts')->count())->toBe(1);
});
