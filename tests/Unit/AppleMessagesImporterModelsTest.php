<?php

declare(strict_types=1);

use App\Models\AppleMessagesAttachment;
use App\Models\AppleMessagesConversation;
use App\Models\AppleMessagesMessage;
use App\Models\AppleMessagesMessageObservation;
use App\Models\AppleMessagesParticipant;
use App\Models\AppleMessagesSourceSet;
use Carbon\CarbonImmutable;

it('maps apple messages importer tables through explicit eloquent model contracts', function (): void {
    $sourceSet = new AppleMessagesSourceSet;
    $conversation = new AppleMessagesConversation([
        'style' => '45',
        'is_archived' => 1,
        'raw_chat' => ['chat_identifier' => 'chat-001'],
    ]);
    $participant = new AppleMessagesParticipant;
    $message = new AppleMessagesMessage([
        'sent_at' => '2026-04-24 08:30:00',
        'read_at' => '2026-04-24 08:31:00',
        'delivered_at' => '2026-04-24 08:32:00',
        'from_me' => 1,
        'is_delivered' => 1,
        'is_read' => 0,
        'is_sent' => 1,
        'item_type' => '0',
        'group_action_type' => '1',
        'reaction_type' => '2000',
        'raw_message' => ['guid' => 'msg-001'],
    ]);
    $attachment = new AppleMessagesAttachment([
        'total_bytes' => '204800',
        'raw_attachment' => ['mime_type' => 'image/jpeg'],
    ]);
    $messageObservation = new AppleMessagesMessageObservation([
        'source_message_row_id' => '42',
    ]);

    expect($sourceSet->getTable())->toBe('apple_messages_source_sets')
        ->and($sourceSet->messageObservations()->getForeignKeyName())->toBe('apple_messages_source_set_id');

    expect($conversation->getTable())->toBe('apple_messages_conversations')
        ->and($conversation->style)->toBe(45)
        ->and($conversation->is_archived)->toBeTrue()
        ->and($conversation->raw_chat)->toBeArray()
        ->and($conversation->messages()->getForeignKeyName())->toBe('apple_messages_conversation_id')
        ->and($conversation->participants()->getForeignPivotKeyName())->toBe('apple_messages_conversation_id')
        ->and($conversation->participants()->getRelatedPivotKeyName())->toBe('apple_messages_participant_id');

    expect($participant->getTable())->toBe('apple_messages_participants')
        ->and($participant->sentMessages()->getForeignKeyName())->toBe('sender_participant_id');

    expect($message->getTable())->toBe('apple_messages_messages')
        ->and($message->sent_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->read_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->delivered_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->from_me)->toBeTrue()
        ->and($message->is_delivered)->toBeTrue()
        ->and($message->is_read)->toBeFalse()
        ->and($message->is_sent)->toBeTrue()
        ->and($message->item_type)->toBe(0)
        ->and($message->group_action_type)->toBe(1)
        ->and($message->reaction_type)->toBe(2000)
        ->and($message->raw_message)->toBeArray()
        ->and($message->conversation()->getForeignKeyName())->toBe('apple_messages_conversation_id')
        ->and($message->sender()->getForeignKeyName())->toBe('sender_participant_id')
        ->and($message->attachments()->getForeignKeyName())->toBe('apple_messages_message_id')
        ->and($message->observations()->getForeignKeyName())->toBe('apple_messages_message_id');

    expect($attachment->getTable())->toBe('apple_messages_attachments')
        ->and($attachment->total_bytes)->toBe(204800)
        ->and($attachment->raw_attachment)->toBeArray()
        ->and($attachment->message()->getForeignKeyName())->toBe('apple_messages_message_id');

    expect($messageObservation->getTable())->toBe('apple_messages_message_observations')
        ->and($messageObservation->source_message_row_id)->toBe(42)
        ->and($messageObservation->message()->getForeignKeyName())->toBe('apple_messages_message_id')
        ->and($messageObservation->sourceSet()->getForeignKeyName())->toBe('apple_messages_source_set_id');
});
