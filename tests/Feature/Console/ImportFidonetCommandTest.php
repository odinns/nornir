<?php

declare(strict_types=1);

use App\Models\FidonetMessage;
use App\Models\IntakeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/fidonet'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports fidonet sources from the cli with useful default output', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-console');

    artisanCommand($this, 'import:fidonet', [
        'source' => $fixture['env_path'],
    ])
        ->expectsOutputToContain('Recording intake for FidoNet source')
        ->expectsOutputToContain('Importing FidoNet source')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(IntakeRecord::query()->count())->toBe(1);
    expect(FidonetMessage::query()->count())->toBe(3);
});
