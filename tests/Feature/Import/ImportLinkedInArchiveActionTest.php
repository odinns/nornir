<?php

declare(strict_types=1);

use App\Actions\Import\ImportLinkedInArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/linkedin'));
    File::deleteDirectory(base_path('data/runs'));
});

it('imports linkedin archive biography slices into canonical linkedin tables', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-import-primary', [
        'messages' => [[
            'CONVERSATION ID' => 'conv-1',
            'CONVERSATION TITLE' => 'Recruiter chat',
            'FROM' => 'Odinn Adalsteinsson',
            'SENDER PROFILE URL' => 'https://www.linkedin.com/in/odinnadalsteinsson',
            'TO' => 'Sylvester Damgaard',
            'RECIPIENT PROFILE URLS' => 'https://www.linkedin.com/in/sylvesterdamgaard',
            'DATE' => '2026-04-08 15:32:31 UTC',
            'SUBJECT' => 'Full Stack role',
            'CONTENT' => 'Hej Sylvester',
            'FOLDER' => 'INBOX',
            'ATTACHMENTS' => 'https://www.linkedin.com/dms/prv/attachment/example',
        ], [
            'CONVERSATION ID' => 'conv-1',
            'CONVERSATION TITLE' => 'Recruiter chat',
            'FROM' => 'Sylvester Damgaard',
            'SENDER PROFILE URL' => 'https://www.linkedin.com/in/sylvesterdamgaard',
            'TO' => 'Odinn Adalsteinsson',
            'RECIPIENT PROFILE URLS' => 'https://www.linkedin.com/in/odinnadalsteinsson',
            'DATE' => '2026-04-09 07:57:01 UTC',
            'SUBJECT' => 'Full Stack role',
            'CONTENT' => 'Hej Odinn',
            'FOLDER' => 'INBOX',
            'ATTACHMENTS' => '',
        ], [
            'CONVERSATION ID' => 'conv-2',
            'CONVERSATION TITLE' => 'Group chat',
            'FROM' => 'Odinn Adalsteinsson',
            'SENDER PROFILE URL' => 'https://www.linkedin.com/in/odinnadalsteinsson',
            'TO' => 'A, B',
            'RECIPIENT PROFILE URLS' => 'https://www.linkedin.com/in/a,https://www.linkedin.com/in/b',
            'DATE' => '2026-04-10 10:00:00 UTC',
            'SUBJECT' => '',
            'CONTENT' => 'Hello group',
            'FOLDER' => 'ARCHIVE',
            'ATTACHMENTS' => '',
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $result = app(ImportLinkedInArchiveAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('linkedin_archives')->count())->toBe(1);
    expect(DB::table('linkedin_profile_snapshots')->count())->toBe(1);
    expect(DB::table('linkedin_positions')->count())->toBe(1);
    expect(DB::table('linkedin_education_records')->count())->toBe(1);
    expect(DB::table('linkedin_projects')->count())->toBe(1);
    expect(DB::table('linkedin_skills')->count())->toBe(2);
    expect(DB::table('linkedin_languages')->count())->toBe(1);
    expect(DB::table('linkedin_people')->count())->toBe(9);
    expect(DB::table('linkedin_connections')->count())->toBe(1);
    expect(DB::table('linkedin_invitations')->count())->toBe(1);
    expect(DB::table('linkedin_recommendations')->count())->toBe(2);
    expect(DB::table('linkedin_endorsements')->count())->toBe(2);
    expect(DB::table('linkedin_shares')->count())->toBe(1);
    expect(DB::table('linkedin_comments')->count())->toBe(1);
    expect(DB::table('linkedin_reactions')->count())->toBe(1);
    expect(DB::table('linkedin_rich_media')->count())->toBe(1);
    expect(DB::table('linkedin_conversations')->count())->toBe(2);
    expect(DB::table('linkedin_messages')->count())->toBe(3);
    expect(DB::table('linkedin_message_attachments')->count())->toBe(1);

    expect(DB::table('linkedin_messages')->orderBy('sent_at')->pluck('content')->all())->toBe([
        'Hej Sylvester',
        'Hej Odinn',
        'Hello group',
    ]);

    expect(DB::table('linkedin_endorsements')->orderBy('direction')->pluck('skill_name', 'direction')->all())->toBe([
        'given' => 'Web Development',
        'received' => 'HTML',
    ]);

    expect(json_decode((string) DB::table('linkedin_message_attachments')->value('attachment_urls_json'), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(['https://www.linkedin.com/dms/prv/attachment/example']);
});

it('reruns idempotently for the same linkedin archive', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-import-repeat');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importer = app(ImportLinkedInArchiveAction::class);

    $firstResult = $importer($intake->dispatchPayload);
    $secondResult = $importer($intake->dispatchPayload);

    expect($secondResult->run->is($firstResult->run))->toBeTrue();
    expect(DB::table('linkedin_archives')->count())->toBe(1);
    expect(DB::table('linkedin_messages')->count())->toBe(2);
    expect(DB::table('linkedin_endorsements')->count())->toBe(2);
    expect($secondResult->summary['inserted_messages'])->toBe(0);
    expect($secondResult->summary['reobserved_messages'])->toBe(2);
});

it('keeps older canonical linkedin rows when a newer export is missing them', function (): void {
    $fullArchive = createLinkedInFixtureArchive('linkedin-import-full', [
        'endorsements_received' => [[
            'Endorsement Date' => '2023/06/05 15:47:58 UTC',
            'Skill Name' => 'HTML',
            'Endorser First Name' => 'Ann',
            'Endorser Last Name' => 'Cross',
            'Endorser Public Url' => 'www.linkedin.com/in/ann-cross-a3a93822a',
            'Endorsement Status' => 'ACCEPTED',
        ]],
    ]);

    $truncatedArchive = createLinkedInFixtureArchive('linkedin-import-truncated', [
        'messages' => [[
            'CONVERSATION ID' => 'conv-1',
            'CONVERSATION TITLE' => 'Recruiter chat',
            'FROM' => 'Sylvester Damgaard',
            'SENDER PROFILE URL' => 'https://www.linkedin.com/in/sylvesterdamgaard',
            'TO' => 'Odinn Adalsteinsson',
            'RECIPIENT PROFILE URLS' => 'https://www.linkedin.com/in/odinnadalsteinsson',
            'DATE' => '2026-04-09 07:57:01 UTC',
            'SUBJECT' => 'Full Stack role',
            'CONTENT' => 'Hej Odinn',
            'FOLDER' => 'INBOX',
            'ATTACHMENTS' => '',
        ]],
        'endorsements_received' => [],
    ]);

    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportLinkedInArchiveAction::class);

    $fullIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fullArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fullArchive['archive_path']],
        ],
        importerOptions: [],
    ));

    $truncatedIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $truncatedArchive['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$truncatedArchive['archive_path']],
        ],
        importerOptions: [],
    ));

    $importer($fullIntake->dispatchPayload);
    $importer($truncatedIntake->dispatchPayload);

    expect(DB::table('linkedin_archives')->count())->toBe(2);
    expect(DB::table('linkedin_messages')->count())->toBe(2);
    expect(DB::table('linkedin_endorsements')->count())->toBe(2);
    expect(DB::table('linkedin_messages')->orderBy('sent_at')->pluck('content')->all())->toBe([
        'Hej Sylvester',
        'Hej Odinn',
    ]);
});

it('fails clearly when a supported linkedin file has the wrong shape', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-import-malformed', [
        'malformed_files' => [
            'Connections.csv' => "Notes:\nThis file is broken on purpose\n",
        ],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    expect(fn () => app(ImportLinkedInArchiveAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'Malformed LinkedIn source file [Connections.csv].');
});

it('stores multiple linkedin message attachments in one json column', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-import-multi-attachments', [
        'messages' => [[
            'CONVERSATION ID' => 'conv-attachments',
            'CONVERSATION TITLE' => 'Attachment chat',
            'FROM' => 'Odinn Adalsteinsson',
            'SENDER PROFILE URL' => 'https://www.linkedin.com/in/odinnadalsteinsson',
            'TO' => 'Sylvester Damgaard',
            'RECIPIENT PROFILE URLS' => 'https://www.linkedin.com/in/sylvesterdamgaard',
            'DATE' => '2026-04-08 15:32:31 UTC',
            'SUBJECT' => 'Attachments',
            'CONTENT' => 'Two files attached',
            'FOLDER' => 'INBOX',
            'ATTACHMENTS' => 'https://www.linkedin.com/dms/prv/vid/v2/D4E06AQEUyna5EXtWKA/messaging-attachmentFile/messaging-attachmentFile/0/1692707192755?m=AQIsqHnHMpdi3AAAAZ139-NCd8g6LbMgzoW8Yb6zyFgmzGqB2VJ0DLM&ne=1&v=beta&t=oXdoVaBGSw4XmSPs2VDLGEgNhlG3HTDODSPzAlBj1NI,https://www.linkedin.com/dms/prv/vid/v2/D4E06AQFRTktwFtzNQA/messaging-attachmentFile/messaging-attachmentFile/0/1692707195936?m=AQJcceoWPNFxUgAAAZ139-NHp47MBkKH1htlA2yAGojH5PI040WpcP8&ne=1&v=beta&t=fTgB6qmIqmwTyimilSm2aDaMuERCciHyzDPOoXVU9As',
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    app(ImportLinkedInArchiveAction::class)($intake->dispatchPayload);

    expect(DB::table('linkedin_message_attachments')->count())->toBe(1);
    expect(json_decode((string) DB::table('linkedin_message_attachments')->value('attachment_urls_json'), true, 512, JSON_THROW_ON_ERROR))->toBe([
        'https://www.linkedin.com/dms/prv/vid/v2/D4E06AQEUyna5EXtWKA/messaging-attachmentFile/messaging-attachmentFile/0/1692707192755?m=AQIsqHnHMpdi3AAAAZ139-NCd8g6LbMgzoW8Yb6zyFgmzGqB2VJ0DLM&ne=1&v=beta&t=oXdoVaBGSw4XmSPs2VDLGEgNhlG3HTDODSPzAlBj1NI',
        'https://www.linkedin.com/dms/prv/vid/v2/D4E06AQFRTktwFtzNQA/messaging-attachmentFile/messaging-attachmentFile/0/1692707195936?m=AQJcceoWPNFxUgAAAZ139-NHp47MBkKH1htlA2yAGojH5PI040WpcP8&ne=1&v=beta&t=fTgB6qmIqmwTyimilSm2aDaMuERCciHyzDPOoXVU9As',
    ]);
});

it('imports messages with very long recipient lists', function (): void {
    $recipients = array_map(
        static fn (int $number): string => 'Synthetic Recipient '.$number,
        range(1, 32),
    );
    $recipientUrls = array_map(
        static fn (int $number): string => 'https://www.linkedin.com/in/synthetic-recipient-'.$number,
        range(1, 32),
    );

    $fixture = createLinkedInFixtureArchive('linkedin-import-long-recipients', [
        'messages' => [[
            'CONVERSATION ID' => 'conv-long-recipients',
            'CONVERSATION TITLE' => '',
            'FROM' => 'Synthetic Sender',
            'SENDER PROFILE URL' => 'https://www.linkedin.com/in/synthetic-sender',
            'TO' => implode(',', $recipients),
            'RECIPIENT PROFILE URLS' => implode(',', $recipientUrls),
            'DATE' => '2023-01-05 05:38:55 UTC',
            'SUBJECT' => '',
            'CONTENT' => 'Okay',
            'FOLDER' => 'INBOX',
            'ATTACHMENTS' => '',
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    app(ImportLinkedInArchiveAction::class)($intake->dispatchPayload);

    expect(DB::table('linkedin_messages')->value('content'))->toBe('Okay');
    expect(DB::table('linkedin_messages')->value('to_display'))->toContain('Synthetic Recipient 1');
});
