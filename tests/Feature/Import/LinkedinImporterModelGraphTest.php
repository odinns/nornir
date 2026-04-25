<?php

declare(strict_types=1);

use App\Models\LinkedinPerson;

require_once __DIR__.'/../../Support/LinkedInFixtures.php';

use App\Actions\Import\ImportLinkedInArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\LinkedinArchive;
use App\Models\LinkedinConversation;
use App\Models\LinkedinMessage;
use App\Models\LinkedinProfileSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/linkedin'));
    File::deleteDirectory(base_path('data/runs'));
});

it('traverses linkedin importer eloquent graph over imported archive data', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-model-graph', [
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

    $archive = LinkedinArchive::query()
        ->with([
            'profileSnapshot.person',
            'conversations.messages.sender',
            'conversations.messages.attachment',
        ])
        ->sole();

    expect($archive->conversations)->toHaveCount(1);

    /** @var LinkedinProfileSnapshot $profileSnapshot */
    $profileSnapshot = $archive->profileSnapshot;

    /** @var LinkedinConversation $conversation */
    $conversation = $archive->conversations->sole();

    expect($profileSnapshot->person)->not->toBeNull();

    /** @var LinkedinPerson $profilePerson */
    $profilePerson = $profileSnapshot->person;

    expect($profilePerson->display_name)->toBe('Odinn Adalsteinsson')
        ->and($conversation->title)->toBe('Recruiter chat')
        ->and($conversation->messages)->toHaveCount(2)
        ->and($conversation->messages->pluck('content')->all())->toBe([
            'Hej Sylvester',
            'Hej Odinn',
        ]);

    $firstMessage = LinkedinMessage::query()
        ->with(['conversation.firstSeenArchive', 'sender.messagesSent', 'attachment'])
        ->orderBy('sent_at')
        ->firstOrFail();

    $firstSeenArchive = $firstMessage->conversation->firstSeenArchive ?? throw new RuntimeException('Expected LinkedIn conversation archive.');
    $sender = $firstMessage->sender ?? throw new RuntimeException('Expected LinkedIn message sender.');
    $attachment = $firstMessage->attachment ?? throw new RuntimeException('Expected LinkedIn message attachment.');

    expect($firstSeenArchive->is($archive))->toBeTrue()
        ->and($sender->display_name)->toBe('Odinn Adalsteinsson')
        ->and($sender->messagesSent->pluck('content')->all())->toContain('Hej Sylvester')
        ->and($attachment->attachment_urls_json)->toBe([
            'https://www.linkedin.com/dms/prv/attachment/example',
        ]);
});
