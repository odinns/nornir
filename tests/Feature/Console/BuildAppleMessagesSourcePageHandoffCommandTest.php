<?php

declare(strict_types=1);

use App\Actions\Import\ImportAppleMessagesAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds an apple messages source-page handoff from the latest successful import run', function (): void {
    $fixture = createAppleMessagesFixtureDatabase('apple-messages-handoff-cli', [
        'messages' => [
            [
                'guid' => 'msg-handoff-cli-001',
                'text' => 'CLI handoff message',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_800_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_800_010),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $fixture['database_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['root_path']],
            'attachments_root' => $fixture['attachments_root'],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportAppleMessagesAction::class)($intake->dispatchPayload);

    $this->artisan('handoff:apple-messages-source-pages')
        ->expectsOutputToContain('Building Apple Messages source-page handoff')
        ->expectsOutputToContain("Using run id: {$importResult->run->id}")
        ->expectsOutputToContain('Source locator: '.$fixture['database_path'])
        ->expectsOutputToContain('Source set count: 1')
        ->expectsOutputToContain('Conversation count: 1')
        ->expectsOutputToContain('Message count: 1')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});
