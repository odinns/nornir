<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/twitter'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports twitter archives from the cli with useful default output', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-console');

    artisanCommand($this, 'import:twitter', [
        'source' => $fixture['archive_path'],
    ])
        ->expectsOutputToContain('Recording intake for Twitter source')
        ->expectsOutputToContain('Importing Twitter archive')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(1);
    expect(DB::table('twitter_tweets')->count())->toBe(2);
});

it('stays quiet when quiet mode is requested', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-console-quiet');

    artisanCommand($this, 'import:twitter', [
        'source' => $fixture['archive_path'],
        '--quiet' => true,
    ])->assertSuccessful();
});
