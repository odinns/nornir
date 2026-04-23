<?php

declare(strict_types=1);

require_once __DIR__.'/../../Support/ChatGptFixtures.php';

use App\Actions\Import\ImportChatGptConversationsAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\ChatGptArchive;
use App\Models\ChatGptConversation;
use App\Models\ChatGptMessage;
use App\Models\ChatGptNode;
use App\Models\ChatGptSourceSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/chatgpt'));
    File::deleteDirectory(base_path('data/runs'));
});

it('traverses chatgpt importer eloquent graph over imported export data', function (): void {
    $exportRoot = createChatGptExportDirectory([
        buildChatGptConversation(),
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $exportRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$exportRoot],
            'relative_glob' => 'conversations-*.json',
        ],
        importerOptions: [],
    ));

    app(ImportChatGptConversationsAction::class)($intake->dispatchPayload);

    $sourceSet = ChatGptSourceSet::query()
        ->with([
            'archives.conversations.nodes.message',
            'archives.conversations.messages.parts',
            'archives.conversations.messages.assets',
            'conversationObservations.conversation',
            'messageObservations.message',
        ])
        ->sole();

    /** @var ChatGptArchive $archive */
    $archive = $sourceSet->archives->sole();

    /** @var ChatGptConversation $conversation */
    $conversation = $archive->conversations->sole();

    $archiveSourceSet = $archive->sourceSet;

    assert($archiveSourceSet instanceof ChatGptSourceSet);

    expect($archiveSourceSet)->not->toBeNull();

    expect($archiveSourceSet->is($sourceSet))->toBeTrue()
        ->and($conversation->archive->is($archive))->toBeTrue()
        ->and($conversation->nodes)->toHaveCount(4)
        ->and($conversation->messages)->toHaveCount(4);

    $assistantMessage = ChatGptMessage::query()
        ->with([
            'conversation.archive.sourceSet',
            'node.conversation',
            'parts',
            'assets',
            'observations.sourceSet',
        ])
        ->where('message_id', 'assistant-conversation-1')
        ->sole();

    $messageArchiveSourceSet = $assistantMessage->conversation->archive->sourceSet;

    assert($messageArchiveSourceSet instanceof ChatGptSourceSet);

    expect($messageArchiveSourceSet)->not->toBeNull();

    expect($assistantMessage->conversation->is($conversation))->toBeTrue()
        ->and($assistantMessage->conversation->archive->is($archive))->toBeTrue()
        ->and($messageArchiveSourceSet->is($sourceSet))->toBeTrue()
        ->and($assistantMessage->node)->not->toBeNull()
        ->and($assistantMessage->node->conversation->is($conversation))->toBeTrue()
        ->and($assistantMessage->parts->pluck('text_part')->filter()->values()->all())->toBe([
            'It is importer o clock.',
            'Still a terrible time for optimism.',
        ])
        ->and($assistantMessage->assets)->toHaveCount(1)
        ->and($assistantMessage->assets->sole()->asset_pointer)->toBe('file-service://file-asset-1')
        ->and($assistantMessage->observations)->toHaveCount(1)
        ->and($assistantMessage->observations->sole()->sourceSet->is($sourceSet))->toBeTrue();

    $assistantNode = $conversation->nodes
        ->firstWhere('node_id', 'assistant-conversation-1');

    assert($assistantNode instanceof ChatGptNode);

    expect($assistantNode)->not->toBeNull();

    expect($assistantNode)->not->toBeNull()
        ->and($assistantNode->message)->not->toBeNull();

    $assistantNodeMessage = $assistantNode->message;

    assert($assistantNodeMessage instanceof ChatGptMessage);

    expect($assistantNodeMessage->is($assistantMessage))->toBeTrue();

    expect($sourceSet->conversationObservations)->toHaveCount(1)
        ->and($sourceSet->conversationObservations->sole()->conversation->is($conversation))->toBeTrue()
        ->and($sourceSet->messageObservations)->toHaveCount(4)
        ->and($sourceSet->messageObservations->pluck('message.message_id')->all())->toContain('assistant-conversation-1');
});
