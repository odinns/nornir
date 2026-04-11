<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/sms'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports sms backups from the cli with useful default output', function (): void {
    $fixture = createSmsFixtureDatabase('sms-console', [
        'messages' => [
            [
                'guid' => 'msg-console-001',
                'text' => 'Console hello',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_600_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_600_010),
                'cache_has_attachments' => 0,
            ],
            [
                'guid' => 'msg-console-002',
                'text' => 'Console reply',
                'is_from_me' => 1,
                'handle_id' => null,
                'date' => appleTimestampForUnix(1_700_600_100),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_600_120),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);
    $contactsDatabase = createAddressBookFixtureDatabase('sms-console-contacts', [[
        'first_name' => 'Console',
        'last_name' => 'Person',
        'organization' => null,
        'phones' => ['+45 11 11 11 11'],
    ]]);

    $this->artisan('import:sms', [
        'source' => $fixture['database_path'],
        '--attachments-root' => $fixture['attachments_root'],
        '--contacts-db' => [$contactsDatabase],
    ])
        ->expectsOutputToContain('Recording intake for SMS source')
        ->expectsOutputToContain('Importing SMS messages')
        ->expectsOutputToContain('Found 1 chats to import')
        ->expectsOutputToContain('[1/1] +4511111111')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(1);
    expect(DB::table('sms_messages')->count())->toBe(2);
    expect(DB::table('sms_participants')->value('display_name'))->toBe('Console Person');
});

it('stays quiet when quiet mode is requested', function (): void {
    $fixture = createSmsFixtureDatabase('sms-console-quiet', [
        'messages' => [
            [
                'guid' => 'msg-console-quiet-001',
                'text' => 'Quiet import',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_700_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_700_010),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);

    $this->artisan('import:sms', [
        'source' => $fixture['database_path'],
        '--attachments-root' => $fixture['attachments_root'],
        '--quiet' => true,
    ])->assertSuccessful();
});
