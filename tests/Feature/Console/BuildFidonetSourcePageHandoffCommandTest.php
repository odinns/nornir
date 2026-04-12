<?php

declare(strict_types=1);

use App\Actions\Import\ImportFidonetSourceAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/fidonet'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('builds a fidonet source-page handoff from the latest successful import run', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-handoff-cli');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    app(ImportFidonetSourceAction::class)($intake->dispatchPayload);

    $this->artisan('handoff:fidonet-source-pages')
        ->expectsOutputToContain('Building FidoNet source-page handoff')
        ->expectsOutputToContain('Source locator: '.$fixture['env_path'])
        ->expectsOutputToContain('Message count: 3')
        ->assertSuccessful();
});
