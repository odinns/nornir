<?php

declare(strict_types=1);

use App\Models\IntakeRecord;
use App\Models\LinkedinMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/linkedin'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports linkedin archives from the cli with useful default output', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-console');

    artisanCommand($this, 'import:linkedin', [
        'source' => $fixture['archive_path'],
    ])
        ->expectsOutputToContain('Recording intake for LinkedIn source')
        ->expectsOutputToContain('Importing LinkedIn archive')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(IntakeRecord::query()->count())->toBe(1);
    expect(LinkedinMessage::query()->count())->toBe(2);
});
