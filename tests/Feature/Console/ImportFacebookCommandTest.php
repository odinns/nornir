<?php

declare(strict_types=1);

use App\Models\FacebookMessage;
use App\Models\IntakeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    artisanCommand($this, 'import:facebook', [
        'source' => $fixture['archive_path'],
    ])
        ->expectsOutputToContain('Recording intake for Facebook source')
        ->expectsOutputToContain('Importing Facebook archive')
        ->expectsOutputToContain('Found 1 threads to import')
        ->expectsOutputToContain('[1/1] consolethread_123')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(IntakeRecord::query()->count())->toBe(1);
    expect(FacebookMessage::query()->count())->toBe(2);
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

    artisanCommand($this, 'import:facebook', [
        'source' => $fixture['archive_path'],
        '--quiet' => true,
    ])->assertSuccessful();
});
