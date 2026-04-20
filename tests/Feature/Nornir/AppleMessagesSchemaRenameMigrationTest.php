<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    RefreshDatabaseState::$migrated = false;
    Artisan::call('migrate:fresh', ['--force' => true]);
});

afterEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
});

it('renames the existing sms schema to apple messages without losing canonical data', function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::dropAllTables();
    Schema::enableForeignKeyConstraints();

    $createSmsSchema = require database_path('migrations/2026_04_11_000001_create_sms_import_tables.php');
    $renameSchema = require database_path('migrations/2026_04_20_000001_rename_sms_import_tables_to_apple_messages.php');

    $createSmsSchema->up();

    $timestamp = now();

    DB::table('sms_source_sets')->insert([
        'id' => 1,
        'source_key' => 'source-key',
        'source_locator' => '/tmp/chat.db',
        'access_mode' => 'archive',
        'attachments_root' => '/tmp/Attachments',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('sms_conversations')->insert([
        'id' => 1,
        'conversation_key' => 'conversation-key',
        'source_guid' => 'chat-guid-001',
        'chat_identifier' => '+4511111111',
        'display_name' => null,
        'room_name' => null,
        'service' => 'iMessage',
        'style' => 45,
        'is_archived' => false,
        'raw_chat' => json_encode(['guid' => 'chat-guid-001'], JSON_THROW_ON_ERROR),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('sms_participants')->insert([
        'id' => 1,
        'identifier' => '+4511111111',
        'service' => 'iMessage',
        'uncanonicalized_identifier' => '+45 11 11 11 11',
        'display_name' => 'Camilla Lee',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('sms_messages')->insert([
        'id' => 1,
        'sms_conversation_id' => 1,
        'sender_participant_id' => 1,
        'canonical_key' => 'message-key',
        'source_guid' => 'msg-001',
        'source_row_id' => 1,
        'sent_at' => $timestamp,
        'read_at' => null,
        'delivered_at' => null,
        'from_me' => false,
        'service' => 'iMessage',
        'text_body' => 'Hello',
        'is_delivered' => true,
        'is_read' => false,
        'is_sent' => true,
        'item_type' => 0,
        'group_title' => null,
        'group_action_type' => null,
        'reaction_to_guid' => null,
        'reaction_type' => null,
        'raw_message' => json_encode(['guid' => 'msg-001'], JSON_THROW_ON_ERROR),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('sms_attachments')->insert([
        'id' => 1,
        'sms_message_id' => 1,
        'attachment_key' => 'attachment-key',
        'source_guid' => 'attachment-001',
        'relative_path' => 'Attachments/00/00/sample.jpg',
        'source_filename' => '~/Library/Messages/Attachments/00/00/sample.jpg',
        'mime_type' => 'image/jpeg',
        'transfer_name' => 'sample.jpg',
        'total_bytes' => 42,
        'raw_attachment' => json_encode(['guid' => 'attachment-001'], JSON_THROW_ON_ERROR),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('sms_conversation_participant')->insert([
        'sms_conversation_id' => 1,
        'sms_participant_id' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('sms_message_observations')->insert([
        'sms_message_id' => 1,
        'sms_source_set_id' => 1,
        'source_message_row_id' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $renameSchema->up();

    expect(Schema::hasTable('sms_messages'))->toBeFalse();
    expect(Schema::hasTable('apple_messages_messages'))->toBeTrue();
    expect(Schema::hasColumn('apple_messages_messages', 'apple_messages_conversation_id'))->toBeTrue();
    expect(Schema::hasColumn('apple_messages_attachments', 'apple_messages_message_id'))->toBeTrue();
    expect(Schema::hasColumn('apple_messages_message_observations', 'apple_messages_source_set_id'))->toBeTrue();

    expect(DB::table('apple_messages_source_sets')->count())->toBe(1);
    expect(DB::table('apple_messages_conversations')->count())->toBe(1);
    expect(DB::table('apple_messages_participants')->count())->toBe(1);
    expect(DB::table('apple_messages_messages')->count())->toBe(1);
    expect(DB::table('apple_messages_attachments')->count())->toBe(1);
    expect(DB::table('apple_messages_message_observations')->count())->toBe(1);

    expect(DB::table('apple_messages_messages')->value('apple_messages_conversation_id'))->toBe(1);
    expect(DB::table('apple_messages_attachments')->value('apple_messages_message_id'))->toBe(1);
    expect(DB::table('apple_messages_messages')->value('text_body'))->toBe('Hello');
});
