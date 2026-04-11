<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/facebook'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports facebook archives from the cli with useful default output', function (): void {
    $fixture = createFacebookFixtureArchive('facebook-console', [
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'consolethread_123',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [
                [
                    'sender_name' => 'Alice Friend',
                    'timestamp_ms' => 1_700_600_000_000,
                    'content' => 'Console hello',
                ],
                [
                    'sender_name' => 'Odinn Test',
                    'timestamp_ms' => 1_700_600_060_000,
                    'content' => 'Console reply',
                ],
            ],
        ]],
    ]);

    $this->artisan('import:facebook', [
        'source' => $fixture['archive_path'],
    ])
        ->expectsOutputToContain('Recording intake for Facebook source')
        ->expectsOutputToContain('Importing Facebook archive')
        ->expectsOutputToContain('Found 1 threads to import')
        ->expectsOutputToContain('[1/1] consolethread_123')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(1);
    expect(DB::table('facebook_messages')->count())->toBe(2);
});

it('stays quiet when quiet mode is requested', function (): void {
    $fixture = createFacebookFixtureArchive('facebook-console-quiet', [
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'quietthread_123',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [[
                'sender_name' => 'Alice Friend',
                'timestamp_ms' => 1_700_700_000_000,
                'content' => 'Quiet import',
            ]],
        ]],
    ]);

    $this->artisan('import:facebook', [
        'source' => $fixture['archive_path'],
        '--quiet' => true,
    ])->assertSuccessful();
});
