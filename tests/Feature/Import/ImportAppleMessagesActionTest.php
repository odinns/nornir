<?php

declare(strict_types=1);

use App\Actions\Import\ImportAppleMessagesAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\ProvenanceLink;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/apple-messages'));
    File::deleteDirectory(base_path('data/runs'));
});

it('imports an apple chat db into canonical apple messages tables', function (): void {
    $fixture = createAppleMessagesFixtureDatabase('apple-messages-import-primary', [
        'messages' => [
            [
                'guid' => 'msg-001',
                'text' => 'First message',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_000_000),
                'date_read' => appleTimestampForUnix(1_700_000_060),
                'date_delivered' => appleTimestampForUnix(1_700_000_030),
                'cache_has_attachments' => 0,
            ],
            [
                'guid' => 'msg-002',
                'text' => 'Reply with attachment',
                'is_from_me' => 1,
                'handle_id' => null,
                'date' => appleTimestampForUnix(1_700_000_120),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_000_150),
                'cache_has_attachments' => 1,
            ],
        ],
        'attachments' => [
            [
                'message_guid' => 'msg-002',
                'guid' => 'attachment-001',
                'filename' => '~/Library/Messages/Attachments/00/00/sample.jpg',
                'mime_type' => 'image/jpeg',
                'transfer_name' => 'sample.jpg',
                'total_bytes' => 42,
            ],
        ],
    ]);
    $contactsDatabase = createAddressBookFixtureDatabase('apple-messages-import-primary-contacts', [[
        'first_name' => 'Camilla',
        'last_name' => 'Lee',
        'organization' => null,
        'phones' => ['+45 11 11 11 11'],
    ]]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $fixture['database_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['root_path']],
            'attachments_root' => $fixture['attachments_root'],
            'contacts_databases' => [$contactsDatabase],
        ],
        importerOptions: [],
    ));

    $result = app(ImportAppleMessagesAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('apple_messages_source_sets')->count())->toBe(1);
    expect(DB::table('apple_messages_conversations')->count())->toBe(1);
    expect(DB::table('apple_messages_participants')->count())->toBe(1);
    expect(DB::table('apple_messages_messages')->count())->toBe(2);
    expect(DB::table('apple_messages_attachments')->count())->toBe(1);

    $messages = DB::table('apple_messages_messages')
        ->orderBy('sent_at')
        ->pluck('text_body')
        ->all();

    expect($messages)->toBe([
        'First message',
        'Reply with attachment',
    ]);

    $participant = DB::table('apple_messages_participants')->first();

    expect($participant)->not->toBeNull();

    if ($participant === null) {
        return;
    }

    expect($participant->identifier)->toBe('+4511111111');
    expect($participant->display_name)->toBe('Camilla Lee');

    $attachment = DB::table('apple_messages_attachments')->first();

    expect($attachment)->not->toBeNull();

    if ($attachment === null) {
        return;
    }

    expect($attachment->relative_path)->toBe('Attachments/00/00/sample.jpg');
});

it('imports text from attributedBody when the message text column is empty', function (): void {
    $fixture = createAppleMessagesFixtureDatabase('apple-messages-import-attributed-body', [
        'messages' => [
            [
                'guid' => 'msg-attributed-001',
                'text' => null,
                'attributed_body' => 'Din forsendelse er blevet tilbageholdt.',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_010_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_010_030),
                'cache_has_attachments' => 0,
                'service' => 'SMS',
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

    app(ImportAppleMessagesAction::class)($intake->dispatchPayload);

    expect(DB::table('apple_messages_messages')->value('text_body'))
        ->toBe('Din forsendelse er blevet tilbageholdt.');
});

it('ignores orphaned message rows that are not linked to a chat', function (): void {
    $fixture = createAppleMessagesFixtureDatabase('apple-messages-import-orphaned-messages', [
        'messages' => [
            [
                'guid' => 'msg-joined-001',
                'text' => 'Visible message',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_020_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_020_030),
                'cache_has_attachments' => 0,
            ],
            [
                'guid' => 'msg-orphan-001',
                'text' => 'Should stay out of canonical tables',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_020_060),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_020_090),
                'cache_has_attachments' => 0,
                'joined' => false,
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

    app(ImportAppleMessagesAction::class)($intake->dispatchPayload);

    expect(DB::table('apple_messages_messages')->pluck('source_guid')->all())->toBe(['msg-joined-001']);
    expect(DB::table('apple_messages_message_observations')->count())->toBe(1);
});

it('reruns idempotently for the same apple messages backup', function (): void {
    $fixture = createAppleMessagesFixtureDatabase('apple-messages-import-repeat', [
        'messages' => [
            [
                'guid' => 'msg-repeat-001',
                'text' => 'Same backup, same message',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_100_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_100_030),
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

    $importer = app(ImportAppleMessagesAction::class);

    $firstResult = $importer($intake->dispatchPayload);
    $secondResult = $importer($intake->dispatchPayload);

    expect($secondResult->run->is($firstResult->run))->toBeTrue();
    expect(DB::table('apple_messages_source_sets')->count())->toBe(1);
    expect(DB::table('apple_messages_messages')->count())->toBe(1);
    expect(DB::table('apple_messages_message_observations')->count())->toBe(1);
    expect($secondResult->summary['inserted_messages'])->toBe(0);
    expect($secondResult->summary['reobserved_messages'])->toBe(1);
});

it('keeps older canonical messages when a newer backup is missing them', function (): void {
    $fullBackup = createAppleMessagesFixtureDatabase('apple-messages-import-full-backup', [
        'messages' => [
            [
                'guid' => 'msg-history-001',
                'text' => 'Old message that must survive',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_200_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_200_010),
                'cache_has_attachments' => 0,
            ],
            [
                'guid' => 'msg-history-002',
                'text' => 'Still present later',
                'is_from_me' => 1,
                'handle_id' => null,
                'date' => appleTimestampForUnix(1_700_200_100),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_200_120),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);

    $truncatedBackup = createAppleMessagesFixtureDatabase('apple-messages-import-truncated-backup', [
        'messages' => [
            [
                'guid' => 'msg-history-002',
                'text' => 'Still present later',
                'is_from_me' => 1,
                'handle_id' => null,
                'date' => appleTimestampForUnix(1_700_200_100),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_200_120),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportAppleMessagesAction::class);

    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $fullBackup['database_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fullBackup['root_path']],
            'attachments_root' => $fullBackup['attachments_root'],
        ],
        importerOptions: [],
    ));
    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $truncatedBackup['database_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedBackup['root_path']],
            'attachments_root' => $truncatedBackup['attachments_root'],
        ],
        importerOptions: [],
    ));

    $importer($fullIntake->dispatchPayload);
    $importer($truncatedIntake->dispatchPayload);

    expect(DB::table('apple_messages_source_sets')->count())->toBe(2);
    expect(DB::table('apple_messages_messages')->count())->toBe(2);
    expect(DB::table('apple_messages_messages')->pluck('source_guid')->all())->toEqualCanonicalizing([
        'msg-history-001',
        'msg-history-002',
    ]);
});

it('backfills missing history when an older backup is imported after a newer one', function (): void {
    $truncatedBackup = createAppleMessagesFixtureDatabase('apple-messages-import-truncated-first', [
        'messages' => [
            [
                'guid' => 'msg-backfill-002',
                'text' => 'Message visible in both backups',
                'is_from_me' => 1,
                'handle_id' => null,
                'date' => appleTimestampForUnix(1_700_300_100),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_300_120),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);

    $fullBackup = createAppleMessagesFixtureDatabase('apple-messages-import-full-second', [
        'messages' => [
            [
                'guid' => 'msg-backfill-001',
                'text' => 'Recovered older message',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_300_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_300_010),
                'cache_has_attachments' => 0,
            ],
            [
                'guid' => 'msg-backfill-002',
                'text' => 'Message visible in both backups',
                'is_from_me' => 1,
                'handle_id' => null,
                'date' => appleTimestampForUnix(1_700_300_100),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_300_120),
                'cache_has_attachments' => 0,
            ],
        ],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportAppleMessagesAction::class);

    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $truncatedBackup['database_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedBackup['root_path']],
            'attachments_root' => $truncatedBackup['attachments_root'],
        ],
        importerOptions: [],
    ));
    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $fullBackup['database_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fullBackup['root_path']],
            'attachments_root' => $fullBackup['attachments_root'],
        ],
        importerOptions: [],
    ));

    $importer($truncatedIntake->dispatchPayload);
    $result = $importer($fullIntake->dispatchPayload);

    expect(DB::table('apple_messages_source_sets')->count())->toBe(2);
    expect(DB::table('apple_messages_messages')->count())->toBe(2);
    expect($result->summary['inserted_messages'])->toBe(1);
    expect($result->summary['reobserved_messages'])->toBe(1);
});

it('fails clearly when the sqlite source is missing required tables', function (): void {
    $root = storage_path('framework/testing/apple-messages-import-broken-'.bin2hex(random_bytes(4)));
    $databasePath = $root.'/chat.db';

    File::ensureDirectoryExists($root);

    $pdo = new PDO('sqlite:'.$databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE chat (ROWID INTEGER PRIMARY KEY AUTOINCREMENT, guid TEXT)');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'apple-messages',
        accessMode: 'archive',
        sourceLocator: $databasePath,
        scopeSnapshot: [
            'accepted_root_paths' => [$root],
        ],
        importerOptions: [],
    ));

    expect(fn () => app(ImportAppleMessagesAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'Malformed Apple Messages source payload: required SQLite tables are missing');

    $failedRun = Run::query()->latest('id')->first();

    expect($failedRun)->not->toBeNull();

    if ($failedRun === null) {
        return;
    }

    expect($failedRun->status)->toBe(Run::STATUS_FAILED);
    expect($failedRun->failure_summary)->toContain('Malformed Apple Messages source payload');
});

it('records importer artifacts and provenance links for imported apple messages rows', function (): void {
    $fixture = createAppleMessagesFixtureDatabase('apple-messages-import-artifacts', [
        'messages' => [
            [
                'guid' => 'msg-artifact-001',
                'text' => 'Artifact message',
                'is_from_me' => 0,
                'handle_id' => 1,
                'date' => appleTimestampForUnix(1_700_900_000),
                'date_read' => null,
                'date_delivered' => appleTimestampForUnix(1_700_900_010),
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

    $result = app(ImportAppleMessagesAction::class)($intake->dispatchPayload);

    $artifactLocators = $result->run->artifacts()->orderBy('id')->pluck('locator')->all();

    expect($artifactLocators)->toHaveCount(2);
    expect($artifactLocators[0])->toContain('data/imports/apple-messages/');
    expect($artifactLocators[1])->toContain('data/runs/import/');

    $links = ProvenanceLink::query()
        ->where('run_id', $result->run->id)
        ->orderBy('id')
        ->get();

    expect($links)->not->toBeEmpty();
    expect($links->pluck('output_target')->contains(fn (string $target): bool => str_contains($target, 'apple_messages_messages:')))->toBeTrue();
    expect($links->pluck('evidence_ref')->contains('chat.db#message:msg-artifact-001'))->toBeTrue();
});
