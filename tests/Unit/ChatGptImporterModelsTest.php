<?php

declare(strict_types=1);

use App\Models\ChatGptArchive;
use App\Models\ChatGptAsset;
use App\Models\ChatGptConversation;
use App\Models\ChatGptConversationObservation;
use App\Models\ChatGptMessage;
use App\Models\ChatGptMessageObservation;
use App\Models\ChatGptMessagePart;
use App\Models\ChatGptNode;
use App\Models\ChatGptSourceSet;
use Carbon\CarbonImmutable;

it('maps chatgpt importer tables through explicit eloquent model contracts', function (): void {
    $sourceSet = new ChatGptSourceSet;
    $archive = new ChatGptArchive;
    $conversation = new ChatGptConversation([
        'source_create_time' => '1697527962.568149',
        'source_update_time' => '1697528705.169732',
        'conversation_created_at' => '2023-10-17 07:32:42',
        'conversation_updated_at' => '2023-10-17 07:45:05',
        'raw_metadata' => ['plugin_ids' => ['alpha']],
    ]);
    $node = new ChatGptNode([
        'child_node_ids' => ['child-1', 'child-2'],
        'raw_node' => ['id' => 'node-1'],
    ]);
    $message = new ChatGptMessage([
        'source_create_time' => '1697528122.017504',
        'source_update_time' => null,
        'end_turn' => 1,
        'message_created_at' => '2023-10-17 07:35:22',
        'message_updated_at' => null,
        'raw_message' => ['id' => 'message-1'],
    ]);
    $part = new ChatGptMessagePart([
        'part_index' => '2',
        'raw_part' => ['text' => 'Still a terrible time for optimism.'],
    ]);
    $asset = new ChatGptAsset([
        'raw_asset' => ['asset_pointer' => 'file-service://file-asset-1'],
    ]);
    $conversationObservation = new ChatGptConversationObservation;
    $messageObservation = new ChatGptMessageObservation;

    expect($sourceSet->getTable())->toBe('chatgpt_source_sets')
        ->and($sourceSet->archives()->getForeignKeyName())->toBe('chatgpt_source_set_id')
        ->and($sourceSet->conversationObservations()->getForeignKeyName())->toBe('chatgpt_source_set_id')
        ->and($sourceSet->messageObservations()->getForeignKeyName())->toBe('chatgpt_source_set_id');

    expect($archive->getTable())->toBe('chatgpt_archives')
        ->and($archive->sourceSet()->getForeignKeyName())->toBe('chatgpt_source_set_id')
        ->and($archive->conversations()->getForeignKeyName())->toBe('chatgpt_archive_id')
        ->and($archive->conversationObservations()->getForeignKeyName())->toBe('chatgpt_archive_id')
        ->and($archive->messageObservations()->getForeignKeyName())->toBe('chatgpt_archive_id');

    expect($conversation->getTable())->toBe('chatgpt_conversations')
        ->and($conversation->source_create_time)->toBe(1697527962.568149)
        ->and($conversation->source_update_time)->toBe(1697528705.169732)
        ->and($conversation->conversation_created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($conversation->conversation_updated_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($conversation->raw_metadata)->toBeArray()
        ->and($conversation->archive()->getForeignKeyName())->toBe('chatgpt_archive_id')
        ->and($conversation->nodes()->getForeignKeyName())->toBe('chatgpt_conversation_id')
        ->and($conversation->messages()->getForeignKeyName())->toBe('chatgpt_conversation_id')
        ->and($conversation->observations()->getForeignKeyName())->toBe('chatgpt_conversation_id');

    expect($node->getTable())->toBe('chatgpt_nodes')
        ->and($node->child_node_ids)->toBeArray()
        ->and($node->raw_node)->toBeArray()
        ->and($node->conversation()->getForeignKeyName())->toBe('chatgpt_conversation_id')
        ->and($node->message()->getForeignKeyName())->toBe('chatgpt_node_id');

    expect($message->getTable())->toBe('chatgpt_messages')
        ->and($message->source_create_time)->toBe(1697528122.017504)
        ->and($message->end_turn)->toBeTrue()
        ->and($message->message_created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->raw_message)->toBeArray()
        ->and($message->conversation()->getForeignKeyName())->toBe('chatgpt_conversation_id')
        ->and($message->node()->getForeignKeyName())->toBe('chatgpt_node_id')
        ->and($message->parts()->getForeignKeyName())->toBe('chatgpt_message_id')
        ->and($message->assets()->getForeignKeyName())->toBe('chatgpt_message_id')
        ->and($message->observations()->getForeignKeyName())->toBe('chatgpt_message_id');

    expect($part->getTable())->toBe('chatgpt_message_parts')
        ->and($part->part_index)->toBe(2)
        ->and($part->raw_part)->toBeArray()
        ->and($part->message()->getForeignKeyName())->toBe('chatgpt_message_id');

    expect($asset->getTable())->toBe('chatgpt_assets')
        ->and($asset->raw_asset)->toBeArray()
        ->and($asset->message()->getForeignKeyName())->toBe('chatgpt_message_id');

    expect($conversationObservation->getTable())->toBe('chatgpt_conversation_observations')
        ->and($conversationObservation->conversation()->getForeignKeyName())->toBe('chatgpt_conversation_id')
        ->and($conversationObservation->sourceSet()->getForeignKeyName())->toBe('chatgpt_source_set_id')
        ->and($conversationObservation->archive()->getForeignKeyName())->toBe('chatgpt_archive_id');

    expect($messageObservation->getTable())->toBe('chatgpt_message_observations')
        ->and($messageObservation->message()->getForeignKeyName())->toBe('chatgpt_message_id')
        ->and($messageObservation->sourceSet()->getForeignKeyName())->toBe('chatgpt_source_set_id')
        ->and($messageObservation->archive()->getForeignKeyName())->toBe('chatgpt_archive_id');
});
