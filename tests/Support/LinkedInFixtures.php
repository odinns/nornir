<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  array{
 *     profile?:array<string, string>,
 *     emails?:list<array<string, string>>,
 *     phones?:list<array<string, string>>,
 *     whatsapp_numbers?:list<array<string, string>>,
 *     registration?:array<string, string>,
 *     positions?:list<array<string, string>>,
 *     education?:list<array<string, string>>,
 *     projects?:list<array<string, string>>,
 *     skills?:list<string>,
 *     languages?:list<array<string, string>>,
 *     recommendations_received?:list<array<string, string>>,
 *     recommendations_given?:list<array<string, string>>,
 *     endorsements_received?:list<array<string, string>>,
 *     endorsements_given?:list<array<string, string>>,
 *     connections?:list<array<string, string>>,
 *     invitations?:list<array<string, string>>,
 *     shares?:list<array<string, string>>,
 *     comments?:list<array<string, string>>,
 *     reactions?:list<array<string, string>>,
 *     rich_media?:list<array<string, string>>,
 *     messages?:list<array<string, string>>,
 *     malformed_files?:array<string, string>
 * }  $dataset
 * @return array{root_path:string, archive_path:string}
 */
function createLinkedInFixtureArchive(string $name, array $dataset = []): array
{
    $root = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));
    $archivePath = $root.'/linkedin';

    File::ensureDirectoryExists($archivePath);
    File::ensureDirectoryExists($archivePath.'/Jobs');
    File::ensureDirectoryExists($archivePath.'/Verifications');

    writeLinkedInCsv(
        $archivePath.'/Profile.csv',
        [[
            'First Name' => 'Odinn',
            'Last Name' => 'Adalsteinsson',
            'Maiden Name' => '',
            'Address' => 'Berings Gade 32, 2.th. 2630 Taastrup Denmark',
            'Birth Date' => 'Feb 17, 1969',
            'Headline' => 'Senior Software Engineer - Systems & Integrations',
            'Summary' => 'Building useful systems without the ceremonial fog.',
            'Industry' => 'Software Development',
            'Zip Code' => '2630',
            'Geo Location' => 'Copenhagen Metropolitan Area',
            'Twitter Handles' => '',
            'Websites' => '[PERSONAL:https://odinns.dk]',
            'Instant Messengers' => '',
        ] + ($dataset['profile'] ?? [])],
        $dataset['malformed_files']['Profile.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Email Addresses.csv',
        $dataset['emails'] ?? [[
            'Email Address' => 'odinn@example.com',
            'Confirmed' => 'Yes',
            'Primary' => 'Yes',
            'Updated On' => '12/21/21, 8:30 AM',
        ]],
        $dataset['malformed_files']['Email Addresses.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/PhoneNumbers.csv',
        $dataset['phones'] ?? [[
            'Extension' => '',
            'Number' => '26154777',
            'Type' => 'Mobile',
        ]],
        $dataset['malformed_files']['PhoneNumbers.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Whatsapp Phone Numbers.csv',
        $dataset['whatsapp_numbers'] ?? [[
            'Number' => '4526154777',
            'Extension' => '',
            'Is_WhatsApp_Number' => 'true',
        ]],
        $dataset['malformed_files']['Whatsapp Phone Numbers.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Registration.csv',
        [$dataset['registration'] ?? [
            'Registered At' => '8/21/05, 4:38 PM',
            'Registration Ip' => '62.242.232.133',
            'Subscription Types' => '',
        ]],
        $dataset['malformed_files']['Registration.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Profile Summary.csv',
        [['Profile Summary' => '']],
        $dataset['malformed_files']['Profile Summary.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Positions.csv',
        $dataset['positions'] ?? [[
            'Company Name' => 'Moxso',
            'Title' => 'Senior Backend Engineer',
            'Description' => 'Worked on Laravel systems and integrations.',
            'Location' => 'Copenhagen',
            'Started On' => 'Jan 2026',
            'Finished On' => 'Mar 2026',
        ]],
        $dataset['malformed_files']['Positions.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Education.csv',
        $dataset['education'] ?? [[
            'School Name' => 'DTU - Technical University of Denmark',
            'Start Date' => '1996',
            'End Date' => '1998',
            'Notes' => 'Electrical engineering.',
            'Degree Name' => 'Bachelor',
            'Activities' => 'Foundation studies.',
        ]],
        $dataset['malformed_files']['Education.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Projects.csv',
        $dataset['projects'] ?? [[
            'Title' => 'Online sign up for DSL and VoIP telephony',
            'Description' => 'Improved signup flow and backend delivery.',
            'Url' => '',
            'Started On' => 'Apr 2009',
            'Finished On' => 'Apr 2010',
        ]],
        $dataset['malformed_files']['Projects.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Skills.csv',
        array_map(static fn (string $name): array => ['Name' => $name], $dataset['skills'] ?? ['System Architecture', 'Laravel']),
        $dataset['malformed_files']['Skills.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Languages.csv',
        $dataset['languages'] ?? [[
            'Name' => 'Danish',
            'Proficiency' => 'Native or bilingual proficiency',
        ]],
        $dataset['malformed_files']['Languages.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Recommendations_Received.csv',
        $dataset['recommendations_received'] ?? [[
            'First Name' => 'Danny',
            'Last Name' => 'Jackson',
            'Company' => 'Worksome',
            'Job Title' => 'Engineering Team Lead',
            'Text' => 'Odinn is sharp and reliable.',
            'Creation Date' => '12/05/25, 06:59 PM',
            'Status' => 'VISIBLE',
        ]],
        $dataset['malformed_files']['Recommendations_Received.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Recommendations_Given.csv',
        $dataset['recommendations_given'] ?? [[
            'First Name' => 'Vic',
            'Last Name' => 'Maranto',
            'Company' => 'CDK Global',
            'Job Title' => 'Lead Customer Success Manager',
            'Text' => 'Vic did excellent work.',
            'Creation Date' => '08/28/08, 11:02 AM',
            'Status' => '',
        ]],
        $dataset['malformed_files']['Recommendations_Given.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Endorsement_Received_Info.csv',
        $dataset['endorsements_received'] ?? [[
            'Endorsement Date' => '2023/06/05 15:47:58 UTC',
            'Skill Name' => 'HTML',
            'Endorser First Name' => 'Ann',
            'Endorser Last Name' => 'Cross',
            'Endorser Public Url' => 'www.linkedin.com/in/ann-cross-a3a93822a',
            'Endorsement Status' => 'ACCEPTED',
        ]],
        $dataset['malformed_files']['Endorsement_Received_Info.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Endorsement_Given_Info.csv',
        $dataset['endorsements_given'] ?? [[
            'Endorsement Date' => '2017/10/24 08:06:57 UTC',
            'Skill Name' => 'Web Development',
            'Endorsee First Name' => 'Morten',
            'Endorsee Last Name' => 'Thorpe',
            'Endorsee Public Url' => 'www.linkedin.com/in/mortenthorpe',
            'Endorsement Status' => 'ACCEPTED',
        ]],
        $dataset['malformed_files']['Endorsement_Given_Info.csv'] ?? null,
    );

    writeLinkedInConnectionsCsv(
        $archivePath.'/Connections.csv',
        $dataset['connections'] ?? [[
            'First Name' => 'Faiza',
            'Last Name' => 'Malik - Sameera',
            'URL' => 'https://www.linkedin.com/in/faiza-malik-sameera-a8baa9284',
            'Email Address' => '',
            'Company' => 'SoftNice',
            'Position' => 'UK Europe IT Recruiter',
            'Connected On' => '08 Apr 2026',
        ]],
        $dataset['malformed_files']['Connections.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Invitations.csv',
        $dataset['invitations'] ?? [[
            'From' => 'Odinn Adalsteinsson',
            'To' => 'Mike Bellika',
            'Sent At' => '2/7/26, 3:06 AM',
            'Message' => '',
            'Direction' => 'OUTGOING',
            'inviterProfileUrl' => 'https://www.linkedin.com/in/odinnadalsteinsson',
            'inviteeProfileUrl' => 'https://www.linkedin.com/in/mikebellika',
        ]],
        $dataset['malformed_files']['Invitations.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Shares.csv',
        $dataset['shares'] ?? [[
            'Date' => '2026-04-01 09:48:27',
            'ShareLink' => 'https://www.linkedin.com/feed/update/urn%3Ali%3Ashare%3A7445044399887044608',
            'ShareCommentary' => 'Speed is not the problem. The system is.',
            'SharedUrl' => '',
            'MediaUrl' => '',
            'Visibility' => 'MEMBER_NETWORK',
        ]],
        $dataset['malformed_files']['Shares.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Comments.csv',
        $dataset['comments'] ?? [[
            'Date' => '2026-04-10 11:38:27',
            'Link' => 'https://www.linkedin.com/feed/update/urn%3Ali%3AugcPost%3A7447593669714579456',
            'Message' => 'Jeg vil meget gerne være med!',
        ]],
        $dataset['malformed_files']['Comments.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Reactions.csv',
        $dataset['reactions'] ?? [[
            'Date' => '2026-04-01 07:21:53',
            'Type' => 'LIKE',
            'Link' => 'https://www.linkedin.com/feed/update/urn%3Ali%3Aactivity%3A7443179788313202688',
        ]],
        $dataset['malformed_files']['Reactions.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/Rich_Media.csv',
        $dataset['rich_media'] ?? [[
            'Date/Time' => 'You uploaded a feed photo on March 8, 2026 at 9:37 AM (GMT)',
            'Media Description' => 'The woman who impacted my career the most is 23.',
            'Media Link' => 'https://www.linkedin.com/posts/example-media',
        ]],
        $dataset['malformed_files']['Rich_Media.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/messages.csv',
        $dataset['messages'] ?? [[
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
        ]],
        $dataset['malformed_files']['messages.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/guide_messages.csv',
        [[
            'CONVERSATION ID' => 'guide-1',
            'CONVERSATION TITLE' => '',
            'FROM' => '',
            'SENDER PROFILE URL' => '',
            'TO' => '',
            'RECIPIENT PROFILE URLS' => '',
            'DATE' => '2026-04-01 13:19:04 UTC',
            'SUBJECT' => '',
            'CONTENT' => 'You would be a top applicant',
            'FOLDER' => 'INBOX',
        ]],
        $dataset['malformed_files']['guide_messages.csv'] ?? null,
    );

    writeLinkedInCsv(
        $archivePath.'/learning_coach_messages.csv',
        [],
        $dataset['malformed_files']['learning_coach_messages.csv'] ?? null,
        ['CONVERSATION ID', 'CONVERSATION TITLE', 'FROM', 'SENDER PROFILE URL', 'TO', 'RECIPIENT PROFILE URLS', 'DATE', 'SUBJECT', 'CONTENT', 'FOLDER'],
    );

    writeLinkedInCsv(
        $archivePath.'/learning_role_play_messages.csv',
        [],
        $dataset['malformed_files']['learning_role_play_messages.csv'] ?? null,
        ['CONVERSATION ID', 'CONVERSATION TITLE', 'FROM', 'SENDER PROFILE URL', 'TO', 'RECIPIENT PROFILE URLS', 'DATE', 'SUBJECT', 'CONTENT', 'FOLDER'],
    );

    return [
        'root_path' => $root,
        'archive_path' => $archivePath,
    ];
}

/**
 * @param  list<array<string, string>>  $rows
 * @param  list<string>|null  $forcedHeaders
 */
function writeLinkedInCsv(string $path, array $rows, ?string $rawContent = null, ?array $forcedHeaders = null): void
{
    File::ensureDirectoryExists(dirname($path));

    if ($rawContent !== null) {
        File::put($path, $rawContent);

        return;
    }

    $handle = fopen($path, 'wb');

    if ($handle === false) {
        throw new RuntimeException("Unable to open fixture CSV [{$path}] for writing.");
    }

    $headers = $forcedHeaders ?? array_keys($rows[0] ?? []);
    fputcsv($handle, $headers);

    foreach ($rows as $row) {
        fputcsv($handle, array_map(
            static fn (string $header): string => $row[$header] ?? '',
            $headers,
        ));
    }

    fclose($handle);
}

/**
 * @param  list<array<string, string>>  $rows
 */
function writeLinkedInConnectionsCsv(string $path, array $rows, ?string $rawContent = null): void
{
    File::ensureDirectoryExists(dirname($path));

    if ($rawContent !== null) {
        File::put($path, $rawContent);

        return;
    }

    $handle = fopen($path, 'wb');

    if ($handle === false) {
        throw new RuntimeException("Unable to open fixture CSV [{$path}] for writing.");
    }

    fputcsv($handle, ['Notes:']);
    fputcsv($handle, ['When exporting your connection data, you may notice missing email addresses.']);
    fputcsv($handle, []);

    $headers = ['First Name', 'Last Name', 'URL', 'Email Address', 'Company', 'Position', 'Connected On'];
    fputcsv($handle, $headers);

    foreach ($rows as $row) {
        fputcsv($handle, array_map(
            static fn (string $header): string => $row[$header] ?? '',
            $headers,
        ));
    }

    fclose($handle);
}
