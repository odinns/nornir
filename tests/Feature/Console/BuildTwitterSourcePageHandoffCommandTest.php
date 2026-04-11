<?php

declare(strict_types=1);

use App\Actions\Import\ImportTwitterArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a twitter source-page handoff from the latest successful import run', function (): void {
    $fixture = createTwitterFixtureArchive('twitter-handoff-cli');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'twitter',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportTwitterArchiveAction::class)($intake->dispatchPayload);

    $this->artisan('handoff:twitter-source-pages')
        ->expectsOutputToContain('Building Twitter source-page handoff')
        ->expectsOutputToContain("Using run id: {$importResult->run->id}")
        ->expectsOutputToContain('Source locator: '.$fixture['archive_path'])
        ->expectsOutputToContain('Source set count: 1')
        ->expectsOutputToContain('Tweet count: 2')
        ->expectsOutputToContain('Note tweet count: 1')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});
