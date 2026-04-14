<?php

declare(strict_types=1);

use App\Actions\Import\ImportLinkedInArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/linkedin'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('builds a linkedin source-page handoff from the cli', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-handoff-cli');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: ['archive_root' => $fixture['archive_path']],
        importerOptions: [],
    ));

    $importResult = app(ImportLinkedInArchiveAction::class)($intake->dispatchPayload);

    $this->artisan('handoff:linkedin-source-pages', [
        '--run-id' => $importResult->run->id,
    ])
        ->expectsOutputToContain('Building LinkedIn source-page handoff')
        ->expectsOutputToContain('Conversation count: 1')
        ->expectsOutputToContain('Message count: 2')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});
