<?php

declare(strict_types=1);

use App\Actions\Import\ImportFacebookArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a facebook source-page handoff from the latest successful import run', function (): void {
    $fixture = createFacebookFixtureArchive('facebook-handoff-cli', [
        'threads' => [[
            'category' => 'inbox',
            'thread_key' => 'handoffclithread_123',
            'participants' => ['Odinn Test', 'Alice Friend'],
            'messages' => [[
                'sender_name' => 'Alice Friend',
                'timestamp_ms' => 1_700_800_000_000,
                'content' => 'CLI handoff message',
            ]],
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'facebook',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportFacebookArchiveAction::class)($intake->dispatchPayload);

    $this->artisan('handoff:facebook-source-pages')
        ->expectsOutputToContain('Building Facebook source-page handoff')
        ->expectsOutputToContain("Using run id: {$importResult->run->id}")
        ->expectsOutputToContain('Source locator: '.$fixture['archive_path'])
        ->expectsOutputToContain('Source set count: 1')
        ->expectsOutputToContain('Conversation count: 1')
        ->expectsOutputToContain('Message count: 1')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});
