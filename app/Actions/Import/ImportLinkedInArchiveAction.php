<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\LinkedInImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\LinkedinComment;
use App\Models\LinkedinConnection;
use App\Models\LinkedinConversation;
use App\Models\LinkedinEducationRecord;
use App\Models\LinkedinEndorsement;
use App\Models\LinkedinInvitation;
use App\Models\LinkedinLanguage;
use App\Models\LinkedinMessage;
use App\Models\LinkedinMessageAttachment;
use App\Models\LinkedinPerson;
use App\Models\LinkedinPosition;
use App\Models\LinkedinProfileSnapshot;
use App\Models\LinkedinProject;
use App\Models\LinkedinReaction;
use App\Models\LinkedinRecommendation;
use App\Models\LinkedinRichMedia;
use App\Models\LinkedinShare;
use App\Models\LinkedinSkill;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ImportLinkedInArchiveAction
{
    private const array REQUIRED_FILES = [
        'Profile.csv',
        'Positions.csv',
        'Connections.csv',
        'messages.csv',
        'Endorsement_Received_Info.csv',
        'Endorsement_Given_Info.csv',
    ];

    /**
     * @var array<string, int>
     */
    private array $personIdCache = [];

    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly SourceObservationStore $sourceObservationStore,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): LinkedInImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'linkedin-import',
            import: fn (Run $run): array => DB::transaction(
                fn (): array => $this->importArchive($dispatchPayload, $run, $progress)
            ),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->importArtifactWriter->write($run, $dispatchPayload, 'linkedin', 'linkedin-import-summary', $summary);
            },
        );

        /** @var array{run: Run, summary: array{source_file:string, source_set_id:int, profile_snapshots:int, positions:int, education_records:int, projects:int, skills:int, languages:int, people:int, connections:int, invitations:int, recommendations:int, endorsements:int, shares:int, comments:int, reactions:int, rich_media:int, conversations:int, messages:int, attachments:int, inserted_messages:int, reobserved_messages:int}} $execution */
        return new LinkedInImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array{source_file:string, source_set_id:int, profile_snapshots:int, positions:int, education_records:int, projects:int, skills:int, languages:int, people:int, connections:int, invitations:int, recommendations:int, endorsements:int, shares:int, comments:int, reactions:int, rich_media:int, conversations:int, messages:int, attachments:int, inserted_messages:int, reobserved_messages:int}
     */
    private function importArchive(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        $archivePath = $this->resolveArchivePath($dispatchPayload);
        $this->assertArchiveShape($archivePath);
        $this->personIdCache = [];

        $archiveId = $this->sourceObservationStore->upsertAndReturnId(
            table: 'linkedin_archives',
            unique: [
                'archive_key' => sha1($archivePath),
            ],
            values: [
                'source_locator' => $archivePath,
                'access_mode' => $dispatchPayload->accessMode,
            ],
        );

        $summary = [
            'source_file' => basename($archivePath),
            'source_set_id' => $archiveId,
            'profile_snapshots' => 0,
            'positions' => 0,
            'education_records' => 0,
            'projects' => 0,
            'skills' => 0,
            'languages' => 0,
            'people' => 0,
            'connections' => 0,
            'invitations' => 0,
            'recommendations' => 0,
            'endorsements' => 0,
            'shares' => 0,
            'comments' => 0,
            'reactions' => 0,
            'rich_media' => 0,
            'conversations' => 0,
            'messages' => 0,
            'attachments' => 0,
            'inserted_messages' => 0,
            'reobserved_messages' => 0,
        ];

        $ownerPersonId = $this->importProfileSnapshot($archivePath, $archiveId);
        $summary['profile_snapshots'] = LinkedinProfileSnapshot::query()
            ->where('linkedin_archive_id', $archiveId)
            ->count();

        $summary['positions'] = $this->importRows(
            archivePath: $archivePath,
            file: 'Positions.csv',
            modelClass: LinkedinPosition::class,
            archiveId: $archiveId,
            keyColumns: ['Company Name', 'Title', 'Started On', 'Finished On', 'Description'],
            valueBuilder: fn (array $row): array => [
                'company_name' => $this->stringValue($row['Company Name'] ?? null),
                'title' => $this->stringValue($row['Title'] ?? null),
                'description' => $this->stringValue($row['Description'] ?? null),
                'location' => $this->stringValue($row['Location'] ?? null),
                'started_on_source' => $this->stringValue($row['Started On'] ?? null),
                'started_on' => $this->parseLinkedInMonthDate($row['Started On'] ?? null),
                'finished_on_source' => $this->stringValue($row['Finished On'] ?? null),
                'finished_on' => $this->parseLinkedInMonthDate($row['Finished On'] ?? null),
                'raw_position' => $row,
            ],
        );

        $summary['education_records'] = $this->importRows(
            archivePath: $archivePath,
            file: 'Education.csv',
            modelClass: LinkedinEducationRecord::class,
            archiveId: $archiveId,
            keyColumns: ['School Name', 'Degree Name', 'Start Date', 'End Date', 'Notes', 'Activities'],
            valueBuilder: fn (array $row): array => [
                'school_name' => $this->stringValue($row['School Name'] ?? null),
                'degree_name' => $this->stringValue($row['Degree Name'] ?? null),
                'start_date_source' => $this->stringValue($row['Start Date'] ?? null),
                'started_on' => $this->parseLinkedInMonthDate($row['Start Date'] ?? null),
                'end_date_source' => $this->stringValue($row['End Date'] ?? null),
                'finished_on' => $this->parseLinkedInMonthDate($row['End Date'] ?? null),
                'notes' => $this->stringValue($row['Notes'] ?? null),
                'activities' => $this->stringValue($row['Activities'] ?? null),
                'raw_record' => $row,
            ],
        );

        $summary['projects'] = $this->importRows(
            archivePath: $archivePath,
            file: 'Projects.csv',
            modelClass: LinkedinProject::class,
            archiveId: $archiveId,
            keyColumns: ['Title', 'Started On', 'Finished On', 'Description', 'Url'],
            valueBuilder: fn (array $row): array => [
                'title' => $this->stringValue($row['Title'] ?? null),
                'description' => $this->stringValue($row['Description'] ?? null),
                'url' => $this->stringValue($row['Url'] ?? null),
                'started_on_source' => $this->stringValue($row['Started On'] ?? null),
                'started_on' => $this->parseLinkedInMonthDate($row['Started On'] ?? null),
                'finished_on_source' => $this->stringValue($row['Finished On'] ?? null),
                'finished_on' => $this->parseLinkedInMonthDate($row['Finished On'] ?? null),
                'raw_project' => $row,
            ],
        );

        $summary['skills'] = $this->importSkills($archivePath, $archiveId);
        $summary['languages'] = $this->importLanguages($archivePath, $archiveId);
        $summary['connections'] = $this->importConnections($archivePath, $archiveId);
        $summary['invitations'] = $this->importInvitations($archivePath, $archiveId);
        $summary['recommendations'] = $this->importRecommendations($archivePath, $archiveId);
        $summary['endorsements'] = $this->importEndorsements($archivePath, $archiveId);
        $summary['shares'] = $this->importShares($archivePath, $archiveId);
        $summary['comments'] = $this->importComments($archivePath, $archiveId);
        $summary['reactions'] = $this->importReactions($archivePath, $archiveId);
        $summary['rich_media'] = $this->importRichMedia($archivePath, $archiveId);

        $messageSummary = $this->importMessages($archivePath, $archiveId, $run, $progress);
        $summary['conversations'] = $messageSummary['conversations'];
        $summary['messages'] = $messageSummary['messages'];
        $summary['attachments'] = $messageSummary['attachments'];
        $summary['inserted_messages'] = $messageSummary['inserted_messages'];
        $summary['reobserved_messages'] = $messageSummary['reobserved_messages'];

        $summary['people'] = LinkedinPerson::query()->count();

        if ($ownerPersonId !== null) {
            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: 'linkedin_profile_snapshots:'.$archiveId,
                claimKey: 'imported-profile',
                evidenceType: 'source-file',
                evidenceRef: 'Profile.csv',
            ));
        }

        return $summary;
    }

    private function resolveArchivePath(ImporterDispatchData $dispatchPayload): string
    {
        if ($dispatchPayload->accessMode !== 'local-path') {
            throw new InvalidArgumentException('LinkedIn imports currently require a local-path archive directory.');
        }

        if (! File::isDirectory($dispatchPayload->sourceLocator)) {
            throw new InvalidArgumentException('Malformed LinkedIn source payload: archive directory was not found.');
        }

        return $dispatchPayload->sourceLocator;
    }

    private function assertArchiveShape(string $archivePath): void
    {
        foreach (self::REQUIRED_FILES as $file) {
            if (! File::exists($archivePath.'/'.$file)) {
                throw new InvalidArgumentException("Malformed LinkedIn source payload: missing required file [{$file}].");
            }
        }
    }

    private function importProfileSnapshot(string $archivePath, int $archiveId): ?int
    {
        $profiles = $this->readCsv($archivePath, 'Profile.csv');
        $profile = $profiles[0] ?? null;

        if (! is_array($profile)) {
            return null;
        }

        $fullName = trim(implode(' ', array_filter([
            $this->stringValue($profile['First Name'] ?? null),
            $this->stringValue($profile['Last Name'] ?? null),
        ])));
        $personId = $fullName !== '' ? $this->upsertPerson($fullName, null) : null;

        $this->upsertModelRow(
            LinkedinProfileSnapshot::class,
            ['linkedin_archive_id' => $archiveId],
            [
                'linkedin_person_id' => $personId,
                'first_name' => $this->stringValue($profile['First Name'] ?? null),
                'last_name' => $this->stringValue($profile['Last Name'] ?? null),
                'full_name' => $fullName !== '' ? $fullName : null,
                'headline' => $this->stringValue($profile['Headline'] ?? null),
                'summary' => $this->stringValue($profile['Summary'] ?? null),
                'industry' => $this->stringValue($profile['Industry'] ?? null),
                'address' => $this->stringValue($profile['Address'] ?? null),
                'zip_code' => $this->stringValue($profile['Zip Code'] ?? null),
                'geo_location' => $this->stringValue($profile['Geo Location'] ?? null),
                'birth_date_source' => $this->stringValue($profile['Birth Date'] ?? null),
                'birth_date' => $this->parseBirthDate($profile['Birth Date'] ?? null),
                'emails_json' => $this->readCsv($archivePath, 'Email Addresses.csv'),
                'phone_numbers_json' => $this->readCsv($archivePath, 'PhoneNumbers.csv'),
                'whatsapp_numbers_json' => $this->readCsv($archivePath, 'Whatsapp Phone Numbers.csv'),
                'registration_at_source' => $this->stringValue($this->readCsv($archivePath, 'Registration.csv')[0]['Registered At'] ?? null),
                'registered_at' => $this->parseSlashDateTime($this->readCsv($archivePath, 'Registration.csv')[0]['Registered At'] ?? null),
                'registration_ip' => $this->stringValue($this->readCsv($archivePath, 'Registration.csv')[0]['Registration Ip'] ?? null),
                'raw_profile' => $profile,
            ],
        );

        return $personId;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $keyColumns
     * @param  callable(array<string, string>): array<string, mixed>  $valueBuilder
     */
    private function importRows(
        string $archivePath,
        string $file,
        string $modelClass,
        int $archiveId,
        array $keyColumns,
        callable $valueBuilder,
    ): int {
        $count = 0;

        foreach ($this->readCsv($archivePath, $file) as $row) {
            $canonicalKey = $this->canonicalKey($row, $keyColumns);

            if ($canonicalKey === null) {
                continue;
            }

            $this->upsertModelRow(
                $modelClass,
                ['canonical_key' => $canonicalKey],
                [
                    'first_seen_linkedin_archive_id' => $archiveId,
                    ...$valueBuilder($row),
                ],
            );
            $count++;
        }

        return $count;
    }

    private function importSkills(string $archivePath, int $archiveId): int
    {
        $count = 0;

        foreach ($this->readCsv($archivePath, 'Skills.csv') as $row) {
            $name = $this->stringValue($row['Name'] ?? null);

            if ($name === null) {
                continue;
            }

            $this->upsertModelRow(
                LinkedinSkill::class,
                ['skill_name' => $name],
                [
                    'first_seen_linkedin_archive_id' => $archiveId,
                ],
            );
            $count++;
        }

        return $count;
    }

    private function importLanguages(string $archivePath, int $archiveId): int
    {
        $count = 0;

        foreach ($this->readCsv($archivePath, 'Languages.csv') as $row) {
            $canonicalKey = $this->canonicalKey($row, ['Name', 'Proficiency']);

            if ($canonicalKey === null) {
                continue;
            }

            $this->upsertModelRow(
                LinkedinLanguage::class,
                ['canonical_key' => $canonicalKey],
                [
                    'first_seen_linkedin_archive_id' => $archiveId,
                    'name' => $this->stringValue($row['Name'] ?? null) ?? '',
                    'proficiency' => $this->stringValue($row['Proficiency'] ?? null),
                ],
            );
            $count++;
        }

        return $count;
    }

    private function importConnections(string $archivePath, int $archiveId): int
    {
        $count = 0;

        foreach ($this->readConnectionsCsv($archivePath, 'Connections.csv') as $row) {
            $name = trim(implode(' ', array_filter([
                $this->stringValue($row['First Name'] ?? null),
                $this->stringValue($row['Last Name'] ?? null),
            ])));

            $personId = $name !== '' ? $this->upsertPerson($name, $this->stringValue($row['URL'] ?? null)) : null;
            $canonicalKey = $this->canonicalKey($row, ['First Name', 'Last Name', 'URL', 'Connected On']);

            if ($canonicalKey === null) {
                continue;
            }

            $this->upsertModelRow(
                LinkedinConnection::class,
                ['canonical_key' => $canonicalKey],
                [
                    'first_seen_linkedin_archive_id' => $archiveId,
                    'linkedin_person_id' => $personId,
                    'email_address' => $this->stringValue($row['Email Address'] ?? null),
                    'company' => $this->stringValue($row['Company'] ?? null),
                    'position' => $this->stringValue($row['Position'] ?? null),
                    'connected_on_source' => $this->stringValue($row['Connected On'] ?? null),
                    'connected_at' => $this->parseConnectionDate($row['Connected On'] ?? null),
                    'raw_connection' => $row,
                ],
            );
            $count++;
        }

        return $count;
    }

    private function importInvitations(string $archivePath, int $archiveId): int
    {
        $count = 0;

        foreach ($this->readCsv($archivePath, 'Invitations.csv') as $row) {
            $senderId = $this->resolvePersonFromNameAndUrl(
                $this->stringValue($row['From'] ?? null),
                $this->stringValue($row['inviterProfileUrl'] ?? null),
            );
            $recipientId = $this->resolvePersonFromNameAndUrl(
                $this->stringValue($row['To'] ?? null),
                $this->stringValue($row['inviteeProfileUrl'] ?? null),
            );
            $canonicalKey = $this->canonicalKey($row, ['From', 'To', 'Sent At', 'Direction', 'Message']);

            if ($canonicalKey === null) {
                continue;
            }

            $this->upsertModelRow(
                LinkedinInvitation::class,
                ['canonical_key' => $canonicalKey],
                [
                    'first_seen_linkedin_archive_id' => $archiveId,
                    'sender_linkedin_person_id' => $senderId,
                    'recipient_linkedin_person_id' => $recipientId,
                    'direction' => $this->stringValue($row['Direction'] ?? null),
                    'sent_at_source' => $this->stringValue($row['Sent At'] ?? null),
                    'sent_at' => $this->parseSlashDateTime($row['Sent At'] ?? null),
                    'message' => $this->stringValue($row['Message'] ?? null),
                    'inviter_profile_url' => $this->stringValue($row['inviterProfileUrl'] ?? null),
                    'invitee_profile_url' => $this->stringValue($row['inviteeProfileUrl'] ?? null),
                    'raw_invitation' => $row,
                ],
            );
            $count++;
        }

        return $count;
    }

    private function importRecommendations(string $archivePath, int $archiveId): int
    {
        $count = 0;

        foreach ([
            'received' => 'Recommendations_Received.csv',
            'given' => 'Recommendations_Given.csv',
        ] as $direction => $file) {
            foreach ($this->readCsv($archivePath, $file) as $row) {
                $name = trim(implode(' ', array_filter([
                    $this->stringValue($row['First Name'] ?? null),
                    $this->stringValue($row['Last Name'] ?? null),
                ])));
                $counterpartId = $name !== '' ? $this->upsertPerson($name, null) : null;
                $canonicalKey = sha1($direction.'|'.($name).'|'.($row['Creation Date'] ?? '').'|'.($row['Text'] ?? ''));

                $this->upsertModelRow(
                    LinkedinRecommendation::class,
                    ['canonical_key' => $canonicalKey],
                    [
                        'first_seen_linkedin_archive_id' => $archiveId,
                        'direction' => $direction,
                        'counterpart_linkedin_person_id' => $counterpartId,
                        'company' => $this->stringValue($row['Company'] ?? null),
                        'job_title' => $this->stringValue($row['Job Title'] ?? null),
                        'text' => $this->stringValue($row['Text'] ?? null),
                        'created_at_source' => $this->stringValue($row['Creation Date'] ?? null),
                        'recommended_at' => $this->parseSlashDateTime($row['Creation Date'] ?? null),
                        'status' => $this->stringValue($row['Status'] ?? null),
                        'raw_recommendation' => $row,
                    ],
                );
                $count++;
            }
        }

        return $count;
    }

    private function importEndorsements(string $archivePath, int $archiveId): int
    {
        $count = 0;

        foreach ([
            'received' => [
                'file' => 'Endorsement_Received_Info.csv',
                'first_name' => 'Endorser First Name',
                'last_name' => 'Endorser Last Name',
                'url' => 'Endorser Public Url',
            ],
            'given' => [
                'file' => 'Endorsement_Given_Info.csv',
                'first_name' => 'Endorsee First Name',
                'last_name' => 'Endorsee Last Name',
                'url' => 'Endorsee Public Url',
            ],
        ] as $direction => $config) {
            foreach ($this->readCsv($archivePath, $config['file']) as $row) {
                $name = trim(implode(' ', array_filter([
                    $this->stringValue($row[$config['first_name']] ?? null),
                    $this->stringValue($row[$config['last_name']] ?? null),
                ])));
                $url = $this->stringValue($row[$config['url']] ?? null);
                $counterpartId = $name !== '' ? $this->upsertPerson($name, $url) : null;
                $canonicalKey = sha1($direction.'|'.($row['Skill Name'] ?? '').'|'.($url ?? $name).'|'.($row['Endorsement Date'] ?? ''));

                $this->upsertModelRow(
                    LinkedinEndorsement::class,
                    ['canonical_key' => $canonicalKey],
                    [
                        'first_seen_linkedin_archive_id' => $archiveId,
                        'direction' => $direction,
                        'counterpart_linkedin_person_id' => $counterpartId,
                        'skill_name' => $this->stringValue($row['Skill Name'] ?? null) ?? '',
                        'endorsed_at_source' => $this->stringValue($row['Endorsement Date'] ?? null),
                        'endorsed_at' => $this->parseUtcDateTime($row['Endorsement Date'] ?? null),
                        'status' => $this->stringValue($row['Endorsement Status'] ?? null),
                        'counterpart_public_url' => $url,
                        'raw_endorsement' => $row,
                    ],
                );
                $count++;
            }
        }

        return $count;
    }

    private function importShares(string $archivePath, int $archiveId): int
    {
        return $this->importRows(
            archivePath: $archivePath,
            file: 'Shares.csv',
            modelClass: LinkedinShare::class,
            archiveId: $archiveId,
            keyColumns: ['Date', 'ShareLink', 'ShareCommentary'],
            valueBuilder: fn (array $row): array => [
                'shared_at_source' => $this->stringValue($row['Date'] ?? null),
                'shared_at' => $this->parseTimestamp($row['Date'] ?? null),
                'share_link' => $this->stringValue($row['ShareLink'] ?? null),
                'commentary' => $this->stringValue($row['ShareCommentary'] ?? null),
                'shared_url' => $this->stringValue($row['SharedUrl'] ?? null),
                'media_url' => $this->stringValue($row['MediaUrl'] ?? null),
                'visibility' => $this->stringValue($row['Visibility'] ?? null),
                'raw_share' => $row,
            ],
        );
    }

    private function importComments(string $archivePath, int $archiveId): int
    {
        return $this->importRows(
            archivePath: $archivePath,
            file: 'Comments.csv',
            modelClass: LinkedinComment::class,
            archiveId: $archiveId,
            keyColumns: ['Date', 'Link', 'Message'],
            valueBuilder: fn (array $row): array => [
                'commented_at_source' => $this->stringValue($row['Date'] ?? null),
                'commented_at' => $this->parseTimestamp($row['Date'] ?? null),
                'link' => $this->stringValue($row['Link'] ?? null),
                'message' => $this->stringValue($row['Message'] ?? null),
                'raw_comment' => $row,
            ],
        );
    }

    private function importReactions(string $archivePath, int $archiveId): int
    {
        return $this->importRows(
            archivePath: $archivePath,
            file: 'Reactions.csv',
            modelClass: LinkedinReaction::class,
            archiveId: $archiveId,
            keyColumns: ['Date', 'Type', 'Link'],
            valueBuilder: fn (array $row): array => [
                'reacted_at_source' => $this->stringValue($row['Date'] ?? null),
                'reacted_at' => $this->parseTimestamp($row['Date'] ?? null),
                'reaction_type' => $this->stringValue($row['Type'] ?? null),
                'link' => $this->stringValue($row['Link'] ?? null),
                'raw_reaction' => $row,
            ],
        );
    }

    private function importRichMedia(string $archivePath, int $archiveId): int
    {
        return $this->importRows(
            archivePath: $archivePath,
            file: 'Rich_Media.csv',
            modelClass: LinkedinRichMedia::class,
            archiveId: $archiveId,
            keyColumns: ['Date/Time', 'Media Description', 'Media Link'],
            valueBuilder: fn (array $row): array => [
                'observed_at_source' => $this->stringValue($row['Date/Time'] ?? null),
                'observed_at' => $this->parseRichMediaTimestamp($row['Date/Time'] ?? null),
                'media_description' => $this->stringValue($row['Media Description'] ?? null),
                'media_link' => $this->stringValue($row['Media Link'] ?? null),
                'raw_media' => $row,
            ],
        );
    }

    /**
     * @return array{conversations:int,messages:int,attachments:int,inserted_messages:int,reobserved_messages:int}
     */
    private function importMessages(string $archivePath, int $archiveId, Run $run, ?callable $progress): array
    {
        $rows = $this->readCsv($archivePath, 'messages.csv');
        $conversationStats = [];
        $summary = [
            'conversations' => 0,
            'messages' => 0,
            'attachments' => 0,
            'inserted_messages' => 0,
            'reobserved_messages' => 0,
        ];

        if ($progress !== null) {
            $progress('messages_resolved', ['total_messages' => count($rows)]);
        }

        foreach ($rows as $row) {
            $sourceConversationId = $this->stringValue($row['CONVERSATION ID'] ?? null);

            if ($sourceConversationId === null) {
                continue;
            }

            $conversationKey = sha1($sourceConversationId);
            $conversation = $this->upsertCanonicalRow(
                LinkedinConversation::class,
                ['conversation_key' => $conversationKey],
                [
                    'first_seen_linkedin_archive_id' => $archiveId,
                    'source_conversation_id' => $sourceConversationId,
                    'title' => $this->stringValue($row['CONVERSATION TITLE'] ?? null),
                    'folder' => $this->stringValue($row['FOLDER'] ?? null),
                ],
            );

            $senderId = $this->resolvePersonFromNameAndUrl(
                $this->stringValue($row['FROM'] ?? null),
                $this->stringValue($row['SENDER PROFILE URL'] ?? null),
            );

            $messageKey = sha1(implode('|', [
                $sourceConversationId,
                $row['DATE'] ?? '',
                $row['FROM'] ?? '',
                $row['TO'] ?? '',
                $row['SUBJECT'] ?? '',
                $row['CONTENT'] ?? '',
                $row['ATTACHMENTS'] ?? '',
            ]));

            $message = $this->upsertCanonicalRow(
                LinkedinMessage::class,
                ['canonical_key' => $messageKey],
                [
                    'linkedin_conversation_id' => $conversation['id'],
                    'first_seen_linkedin_archive_id' => $archiveId,
                    'sender_linkedin_person_id' => $senderId,
                    'sent_at_source' => $this->stringValue($row['DATE'] ?? null),
                    'sent_at' => $this->parseUtcDateTime($row['DATE'] ?? null),
                    'subject' => $this->stringValue($row['SUBJECT'] ?? null),
                    'content' => $this->stringValue($row['CONTENT'] ?? null),
                    'to_display' => $this->stringValue($row['TO'] ?? null),
                    'recipient_profile_urls' => $this->stringValue($row['RECIPIENT PROFILE URLS'] ?? null),
                    'folder' => $this->stringValue($row['FOLDER'] ?? null),
                    'raw_message' => $row,
                ],
            );

            if ($message['wasRecentlyCreated']) {
                $summary['inserted_messages']++;
            } else {
                $summary['reobserved_messages']++;
            }

            $conversationStats[$conversation['id']][] = $this->parseUtcDateTime($row['DATE'] ?? null);

            $attachmentUrls = $this->splitAttachmentUrls($row['ATTACHMENTS'] ?? null);

            if ($attachmentUrls !== []) {
                $this->upsertModelRow(
                    LinkedinMessageAttachment::class,
                    ['attachment_key' => sha1($messageKey)],
                    [
                        'linkedin_message_id' => $message['id'],
                        'attachment_urls_json' => $attachmentUrls,
                    ],
                );
                $summary['attachments'] += count($attachmentUrls);
            }

            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: 'linkedin_messages:'.$message['id'],
                claimKey: 'imported-message',
                evidenceType: 'source-file',
                evidenceRef: 'messages.csv#'.$messageKey,
            ));

            $summary['messages']++;
        }

        foreach ($conversationStats as $conversationId => $timestamps) {
            $timestamps = array_values(array_filter($timestamps));

            LinkedinConversation::query()
                ->whereKey($conversationId)
                ->update([
                    'message_count' => count($timestamps),
                    'first_message_at' => $timestamps === [] ? null : min($timestamps),
                    'last_message_at' => $timestamps === [] ? null : max($timestamps),
                    'updated_at' => now(),
                ]);
        }

        $summary['conversations'] = count($conversationStats);

        return $summary;
    }

    private function upsertPerson(?string $displayName, ?string $profileUrl): int
    {
        $normalizedName = $displayName === null ? null : mb_strtolower(trim($displayName));
        $personKey = sha1(($profileUrl ?? '').'|'.($normalizedName ?? ''));

        if (array_key_exists($personKey, $this->personIdCache)) {
            return $this->personIdCache[$personKey];
        }

        $person = $this->upsertCanonicalRow(
            LinkedinPerson::class,
            [
                'person_key' => $personKey,
            ],
            [
                'display_name' => $displayName ?? 'Unknown LinkedIn person',
                'normalized_name' => $normalizedName,
                'profile_url' => $profileUrl,
            ],
        );
        $personId = $person['id'];

        $this->personIdCache[$personKey] = $personId;

        return $personId;
    }

    private function resolvePersonFromNameAndUrl(?string $displayName, ?string $profileUrl): ?int
    {
        if ($displayName === null && $profileUrl === null) {
            return null;
        }

        return $this->upsertPerson($displayName, $profileUrl);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    private function upsertModelRow(string $modelClass, array $unique, array $values): Model
    {
        return $modelClass::query()->updateOrCreate($unique, [
            ...$values,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     * @return array{id:int,wasRecentlyCreated:bool}
     */
    private function upsertCanonicalRow(string $modelClass, array $unique, array $values): array
    {
        $row = $this->upsertModelRow($modelClass, $unique, $values);

        return [
            'id' => (int) $row->getKey(),
            'wasRecentlyCreated' => $row->wasRecentlyCreated,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $archivePath, string $file): array
    {
        $path = $archivePath.'/'.$file;

        if (! File::exists($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Malformed LinkedIn source file [{$file}].");
        }

        $header = fgetcsv($handle, escape: '\\');

        if ($header === false) {
            fclose($handle);

            throw new InvalidArgumentException("Malformed LinkedIn source file [{$file}].");
        }

        $header = array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $header,
        );

        $rows = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }
            if (count(array_filter($row, static fn (mixed $value): bool => is_string($value) && trim($value) !== '')) === 0) {
                continue;
            }
            $rows[] = $this->combineRow($header, $row, $file);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private function readConnectionsCsv(string $archivePath, string $file): array
    {
        $path = $archivePath.'/'.$file;

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Malformed LinkedIn source file [{$file}].");
        }

        $headers = null;
        $rows = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if ($headers === null) {
                if (($row[0] ?? null) === 'First Name' && ($row[1] ?? null) === 'Last Name') {
                    $headers = array_map(
                        static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                        $row,
                    );
                }

                continue;
            }
            if ($row === [null]) {
                continue;
            }
            if (count(array_filter($row, static fn (mixed $value): bool => is_string($value) && trim($value) !== '')) === 0) {
                continue;
            }

            $rows[] = $this->combineRow($headers, $row, $file);
        }

        fclose($handle);

        if ($headers === null) {
            throw new InvalidArgumentException("Malformed LinkedIn source file [{$file}].");
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $row
     * @return array<string, string>
     */
    private function combineRow(array $headers, array $row, string $file): array
    {
        if (count($row) > count($headers)) {
            throw new InvalidArgumentException("Malformed LinkedIn source file [{$file}].");
        }

        $row = array_pad($row, count($headers), '');

        return array_combine($headers, array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $row,
        ));
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $columns
     */
    private function canonicalKey(array $row, array $columns): ?string
    {
        $parts = [];

        foreach ($columns as $column) {
            $parts[] = $this->stringValue($row[$column] ?? null) ?? '';
        }

        if (count(array_filter($parts, static fn (string $value): bool => $value !== '')) === 0) {
            return null;
        }

        return sha1(implode('|', $parts));
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    private function splitAttachmentUrls(mixed $value): array
    {
        $value = $this->stringValue($value);

        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            explode(',', $value),
        ), static fn (string $url): bool => $url !== ''));
    }

    private function parseTimestamp(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toDateTimeString();
    }

    private function parseUtcDateTime(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toDateTimeString();
    }

    private function parseSlashDateTime(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toDateTimeString();
    }

    private function parseBirthDate(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value === null ? null : CarbonImmutable::parse($value)->toDateString();
    }

    private function parseConnectionDate(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        if ($value === null) {
            return null;
        }

        $timestamp = CarbonImmutable::createFromFormat('d M Y', $value, 'UTC');

        return $timestamp?->startOfDay()->toDateTimeString();
    }

    private function parseLinkedInMonthDate(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value) === 1) {
            $timestamp = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value.'-01-01 00:00:00', 'UTC');

            return $timestamp?->toDateTimeString();
        }

        $timestamp = CarbonImmutable::createFromFormat('M Y', $value, 'UTC');

        return $timestamp?->startOfMonth()->toDateTimeString();
    }

    private function parseRichMediaTimestamp(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/on (.+) \\(GMT\\)$/', $value, $matches) !== 1) {
            return null;
        }

        return CarbonImmutable::parse($matches[1], 'UTC')->utc()->toDateTimeString();
    }
}
