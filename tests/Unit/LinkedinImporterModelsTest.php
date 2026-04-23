<?php

declare(strict_types=1);

use App\Models\LinkedinArchive;
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
use Carbon\CarbonImmutable;

it('maps linkedin importer tables through explicit eloquent model contracts', function (): void {
    $archive = new LinkedinArchive;
    $person = new LinkedinPerson;
    $profileSnapshot = new LinkedinProfileSnapshot([
        'birth_date' => '1988-07-14',
        'registered_at' => '2026-04-08 15:32:31',
        'emails_json' => ['odinn@example.com'],
        'phone_numbers_json' => ['+45 11111111'],
        'whatsapp_numbers_json' => ['+45 22222222'],
        'raw_profile' => ['summary' => 'Builder'],
    ]);
    $position = new LinkedinPosition([
        'started_on' => '2021-01-01 00:00:00',
        'finished_on' => '2023-01-01 00:00:00',
        'raw_position' => ['title' => 'Engineer'],
    ]);
    $educationRecord = new LinkedinEducationRecord([
        'started_on' => '2011-09-01 00:00:00',
        'finished_on' => '2014-06-01 00:00:00',
        'raw_record' => ['schoolName' => 'KEA'],
    ]);
    $project = new LinkedinProject([
        'started_on' => '2024-01-01 00:00:00',
        'finished_on' => '2024-06-01 00:00:00',
        'raw_project' => ['title' => 'Nornir'],
    ]);
    $skill = new LinkedinSkill;
    $language = new LinkedinLanguage;
    $connection = new LinkedinConnection([
        'connected_at' => '2024-03-20 09:15:00',
        'raw_connection' => ['First Name' => 'Faiza'],
    ]);
    $invitation = new LinkedinInvitation([
        'sent_at' => '2024-05-01 12:00:00',
        'raw_invitation' => ['Direction' => 'sent'],
    ]);
    $recommendation = new LinkedinRecommendation([
        'recommended_at' => '2024-05-02 12:00:00',
        'raw_recommendation' => ['Company' => 'Moxso'],
    ]);
    $endorsement = new LinkedinEndorsement([
        'endorsed_at' => '2024-05-03 12:00:00',
        'raw_endorsement' => ['Skill Name' => 'HTML'],
    ]);
    $conversation = new LinkedinConversation([
        'message_count' => '3',
        'first_message_at' => '2026-04-08 15:32:31',
        'last_message_at' => '2026-04-09 07:57:01',
    ]);
    $message = new LinkedinMessage([
        'sent_at' => '2026-04-08 15:32:31',
        'raw_message' => ['CONTENT' => 'Hej Sylvester'],
    ]);
    $messageAttachment = new LinkedinMessageAttachment([
        'attachment_urls_json' => ['https://www.linkedin.com/dms/prv/attachment/example'],
    ]);
    $share = new LinkedinShare([
        'shared_at' => '2024-10-01 08:00:00',
        'raw_share' => ['Commentary' => 'Launch day'],
    ]);
    $comment = new LinkedinComment([
        'commented_at' => '2024-10-02 08:00:00',
        'raw_comment' => ['Message' => 'Nice work'],
    ]);
    $reaction = new LinkedinReaction([
        'reacted_at' => '2024-10-03 08:00:00',
        'raw_reaction' => ['Type' => 'LIKE'],
    ]);
    $richMedia = new LinkedinRichMedia([
        'observed_at' => '2024-10-04 08:00:00',
        'raw_media' => ['Media Link' => 'https://www.linkedin.com/posts/example-media'],
    ]);

    expect($archive->getTable())->toBe('linkedin_archives')
        ->and($archive->profileSnapshot()->getForeignKeyName())->toBe('linkedin_archive_id')
        ->and($archive->positions()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->educationRecords()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->projects()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->skills()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->languages()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->connections()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->invitations()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->recommendations()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->endorsements()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->conversations()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->messages()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->shares()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->comments()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->reactions()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($archive->richMedia()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($person->getTable())->toBe('linkedin_people')
        ->and($person->profileSnapshots()->getForeignKeyName())->toBe('linkedin_person_id')
        ->and($person->connections()->getForeignKeyName())->toBe('linkedin_person_id')
        ->and($person->sentInvitations()->getForeignKeyName())->toBe('sender_linkedin_person_id')
        ->and($person->receivedInvitations()->getForeignKeyName())->toBe('recipient_linkedin_person_id')
        ->and($person->recommendations()->getForeignKeyName())->toBe('counterpart_linkedin_person_id')
        ->and($person->endorsements()->getForeignKeyName())->toBe('counterpart_linkedin_person_id')
        ->and($person->messagesSent()->getForeignKeyName())->toBe('sender_linkedin_person_id');

    expect($profileSnapshot->getTable())->toBe('linkedin_profile_snapshots')
        ->and($profileSnapshot->birth_date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($profileSnapshot->registered_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($profileSnapshot->emails_json)->toBeArray()
        ->and($profileSnapshot->phone_numbers_json)->toBeArray()
        ->and($profileSnapshot->whatsapp_numbers_json)->toBeArray()
        ->and($profileSnapshot->raw_profile)->toBeArray()
        ->and($profileSnapshot->archive()->getForeignKeyName())->toBe('linkedin_archive_id')
        ->and($profileSnapshot->person()->getForeignKeyName())->toBe('linkedin_person_id');

    expect($position->getTable())->toBe('linkedin_positions')
        ->and($position->started_on)->toBeInstanceOf(CarbonImmutable::class)
        ->and($position->finished_on)->toBeInstanceOf(CarbonImmutable::class)
        ->and($position->raw_position)->toBeArray()
        ->and($position->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($educationRecord->getTable())->toBe('linkedin_education_records')
        ->and($educationRecord->started_on)->toBeInstanceOf(CarbonImmutable::class)
        ->and($educationRecord->finished_on)->toBeInstanceOf(CarbonImmutable::class)
        ->and($educationRecord->raw_record)->toBeArray()
        ->and($educationRecord->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($project->getTable())->toBe('linkedin_projects')
        ->and($project->started_on)->toBeInstanceOf(CarbonImmutable::class)
        ->and($project->finished_on)->toBeInstanceOf(CarbonImmutable::class)
        ->and($project->raw_project)->toBeArray()
        ->and($project->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($skill->getTable())->toBe('linkedin_skills')
        ->and($skill->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($language->getTable())->toBe('linkedin_languages')
        ->and($language->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($connection->getTable())->toBe('linkedin_connections')
        ->and($connection->connected_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($connection->raw_connection)->toBeArray()
        ->and($connection->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($connection->person()->getForeignKeyName())->toBe('linkedin_person_id');

    expect($invitation->getTable())->toBe('linkedin_invitations')
        ->and($invitation->sent_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($invitation->raw_invitation)->toBeArray()
        ->and($invitation->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($invitation->sender()->getForeignKeyName())->toBe('sender_linkedin_person_id')
        ->and($invitation->recipient()->getForeignKeyName())->toBe('recipient_linkedin_person_id');

    expect($recommendation->getTable())->toBe('linkedin_recommendations')
        ->and($recommendation->recommended_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($recommendation->raw_recommendation)->toBeArray()
        ->and($recommendation->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($recommendation->counterpart()->getForeignKeyName())->toBe('counterpart_linkedin_person_id');

    expect($endorsement->getTable())->toBe('linkedin_endorsements')
        ->and($endorsement->endorsed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($endorsement->raw_endorsement)->toBeArray()
        ->and($endorsement->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($endorsement->counterpart()->getForeignKeyName())->toBe('counterpart_linkedin_person_id');

    expect($conversation->getTable())->toBe('linkedin_conversations')
        ->and($conversation->message_count)->toBe(3)
        ->and($conversation->first_message_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($conversation->last_message_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($conversation->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($conversation->messages()->getForeignKeyName())->toBe('linkedin_conversation_id');

    expect($message->getTable())->toBe('linkedin_messages')
        ->and($message->sent_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->raw_message)->toBeArray()
        ->and($message->conversation()->getForeignKeyName())->toBe('linkedin_conversation_id')
        ->and($message->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id')
        ->and($message->sender()->getForeignKeyName())->toBe('sender_linkedin_person_id')
        ->and($message->attachment()->getForeignKeyName())->toBe('linkedin_message_id');

    expect($messageAttachment->getTable())->toBe('linkedin_message_attachments')
        ->and($messageAttachment->attachment_urls_json)->toBeArray()
        ->and($messageAttachment->message()->getForeignKeyName())->toBe('linkedin_message_id');

    expect($share->getTable())->toBe('linkedin_shares')
        ->and($share->shared_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($share->raw_share)->toBeArray()
        ->and($share->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($comment->getTable())->toBe('linkedin_comments')
        ->and($comment->commented_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($comment->raw_comment)->toBeArray()
        ->and($comment->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($reaction->getTable())->toBe('linkedin_reactions')
        ->and($reaction->reacted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($reaction->raw_reaction)->toBeArray()
        ->and($reaction->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');

    expect($richMedia->getTable())->toBe('linkedin_rich_media')
        ->and($richMedia->observed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($richMedia->raw_media)->toBeArray()
        ->and($richMedia->firstSeenArchive()->getForeignKeyName())->toBe('first_seen_linkedin_archive_id');
});
