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
    $fixture = createLinkedInFixtureArchive('linkedin-import-long-recipients', [
        'messages' => [[
            'CONVERSATION ID' => 'conv-long-recipients',
            'CONVERSATION TITLE' => '',
            'FROM' => 'ibrahim aminu',
            'SENDER PROFILE URL' => 'https://www.linkedin.com/in/ibrahim-aminu-b460aa9',
            'TO' => 'BILYAMINU ALIYU,LinkedIn Member,Aladetohun Ayomide,LinkedIn Member,Sam Antori,Idiare Atimomo,Frank Addai,LinkedIn Member,Al Stansfield (The Blind Marketer),LinkedIn Member,LinkedIn Member,zayyad abubakar,Yemi Arawore,FAISAL ABUKUR,Adeniji Ayodeji,LinkedIn Member,Suzanna Abbott,aipl amnesh,ALH XIXIMOH Adamu,Mansir Abubakar,Carmen Loreto Acevedo Santana,Manie Amari,Andy Harrington (LION)andy@powertoachieve.co.uk,Zakareya Allach,Alex Albert,Diwan Syed Ehsan Ahmed,Tech Vlogging,Malik Anas,LinkedIn Member,Balkisu Abidina,Dawn Abraham,Florina Alazaroaei,David Alex,dando abubaker,muhammad adamu,Julie Arellano,Temogen Amato',
            'RECIPIENT PROFILE URLS' => 'https://www.linkedin.com/in/bilyaminu-aliyu-1a31384b,https://www.linkedin.com/in/aladetohun-ayomide-423171b,https://www.linkedin.com/in/samantori,https://www.linkedin.com/in/idiare-atimomo,https://www.linkedin.com/in/yournetbizlive,https://www.linkedin.com/in/theblindmarketer,https://www.linkedin.com/in/zayyad-abubakar-710004a6,https://www.linkedin.com/in/yemi-arawore-4780089,https://www.linkedin.com/in/faisal-abukur-5aba7126,https://www.linkedin.com/in/adenijiayodeji,https://www.linkedin.com/in/suzannaabbott,https://www.linkedin.com/in/aipl-amnesh-303a2624,https://www.linkedin.com/in/alh-xiximoh-adamu-65655079,https://www.linkedin.com/in/mansir-abubakar-5aa82983,https://www.linkedin.com/in/carmen-loreto-acevedo-santana-14a04930,https://www.linkedin.com/in/manie-amari-a742a354,https://www.linkedin.com/in/andy-harrington-lion-andy-powertoachieve-co-uk-bb4294b,https://www.linkedin.com/in/zakareya-allach-6a18009b,https://www.linkedin.com/in/mralexalbert,https://www.linkedin.com/in/diwan-syed-ehsan-ahmed-33952641,https://www.linkedin.com/in/techvlogging,https://www.linkedin.com/in/malik-anas-1805255b,https://www.linkedin.com/in/balkisu-abidina-7446b22b,https://www.linkedin.com/in/qualifiedlifecoach,https://www.linkedin.com/in/florina-alazaroaei-40a38bb1,https://www.linkedin.com/in/dismart,https://www.linkedin.com/in/dando-abubaker-7b4a5ba8,https://www.linkedin.com/in/muhammad-adamu-60266214,https://www.linkedin.com/in/julie-arellano-b89a3749,https://www.linkedin.com/in/temogen',
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
    expect(DB::table('linkedin_messages')->value('to_display'))->toContain('BILYAMINU ALIYU');
});
