<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sms_source_sets') || Schema::hasTable('apple_messages_source_sets')) {
            return;
        }

        $this->renameTablesToAppleMessages();
        $this->dropSmsForeignKeys();
        $this->dropSmsUniqueIndexes();
        $this->renameColumnsToAppleMessages();
        $this->renameIndexesToAppleMessages();
        $this->createAppleMessagesUniqueIndexes();
        $this->createAppleMessagesForeignKeys();
    }

    public function down(): void
    {
        if (! Schema::hasTable('apple_messages_source_sets') || Schema::hasTable('sms_source_sets')) {
            return;
        }

        $this->dropAppleMessagesForeignKeys();
        $this->dropAppleMessagesUniqueIndexes();
        $this->renameIndexesToSms();
        $this->renameColumnsToSms();
        $this->renameTablesToSms();
        $this->createSmsUniqueIndexes();
        $this->createSmsForeignKeys();
    }

    private function renameTablesToAppleMessages(): void
    {
        Schema::rename('sms_source_sets', 'apple_messages_source_sets');
        Schema::rename('sms_conversations', 'apple_messages_conversations');
        Schema::rename('sms_participants', 'apple_messages_participants');
        Schema::rename('sms_messages', 'apple_messages_messages');
        Schema::rename('sms_attachments', 'apple_messages_attachments');
        Schema::rename('sms_conversation_participant', 'apple_messages_conversation_participant');
        Schema::rename('sms_message_observations', 'apple_messages_message_observations');
    }

    private function renameTablesToSms(): void
    {
        Schema::rename('apple_messages_source_sets', 'sms_source_sets');
        Schema::rename('apple_messages_conversations', 'sms_conversations');
        Schema::rename('apple_messages_participants', 'sms_participants');
        Schema::rename('apple_messages_messages', 'sms_messages');
        Schema::rename('apple_messages_attachments', 'sms_attachments');
        Schema::rename('apple_messages_conversation_participant', 'sms_conversation_participant');
        Schema::rename('apple_messages_message_observations', 'sms_message_observations');
    }

    private function dropSmsForeignKeys(): void
    {
        DB::statement('ALTER TABLE apple_messages_messages DROP FOREIGN KEY sms_messages_sms_conversation_id_foreign');
        DB::statement('ALTER TABLE apple_messages_messages DROP FOREIGN KEY sms_messages_sender_participant_id_foreign');
        DB::statement('ALTER TABLE apple_messages_attachments DROP FOREIGN KEY sms_attachments_sms_message_id_foreign');
        DB::statement('ALTER TABLE apple_messages_conversation_participant DROP FOREIGN KEY sms_conversation_participant_sms_conversation_id_foreign');
        DB::statement('ALTER TABLE apple_messages_conversation_participant DROP FOREIGN KEY sms_conversation_participant_sms_participant_id_foreign');
        DB::statement('ALTER TABLE apple_messages_message_observations DROP FOREIGN KEY sms_message_observations_sms_message_id_foreign');
        DB::statement('ALTER TABLE apple_messages_message_observations DROP FOREIGN KEY sms_message_observations_sms_source_set_id_foreign');
    }

    private function dropAppleMessagesForeignKeys(): void
    {
        DB::statement('ALTER TABLE apple_messages_messages DROP FOREIGN KEY am_messages_conversation_fk');
        DB::statement('ALTER TABLE apple_messages_messages DROP FOREIGN KEY am_messages_sender_participant_fk');
        DB::statement('ALTER TABLE apple_messages_attachments DROP FOREIGN KEY am_attachments_message_fk');
        DB::statement('ALTER TABLE apple_messages_conversation_participant DROP FOREIGN KEY am_conv_participant_conversation_fk');
        DB::statement('ALTER TABLE apple_messages_conversation_participant DROP FOREIGN KEY am_conv_participant_participant_fk');
        DB::statement('ALTER TABLE apple_messages_message_observations DROP FOREIGN KEY am_message_observation_message_fk');
        DB::statement('ALTER TABLE apple_messages_message_observations DROP FOREIGN KEY am_message_observation_source_set_fk');
    }

    private function dropSmsUniqueIndexes(): void
    {
        DB::statement('ALTER TABLE apple_messages_conversation_participant DROP INDEX sms_conversation_participant_unique');
        DB::statement('ALTER TABLE apple_messages_message_observations DROP INDEX sms_message_observations_unique');
    }

    private function dropAppleMessagesUniqueIndexes(): void
    {
        DB::statement('ALTER TABLE apple_messages_conversation_participant DROP INDEX apple_messages_conversation_participant_unique');
        DB::statement('ALTER TABLE apple_messages_message_observations DROP INDEX apple_messages_message_observations_unique');
    }

    private function renameColumnsToAppleMessages(): void
    {
        DB::statement('ALTER TABLE apple_messages_messages RENAME COLUMN sms_conversation_id TO apple_messages_conversation_id');
        DB::statement('ALTER TABLE apple_messages_attachments RENAME COLUMN sms_message_id TO apple_messages_message_id');
        DB::statement('ALTER TABLE apple_messages_conversation_participant RENAME COLUMN sms_conversation_id TO apple_messages_conversation_id');
        DB::statement('ALTER TABLE apple_messages_conversation_participant RENAME COLUMN sms_participant_id TO apple_messages_participant_id');
        DB::statement('ALTER TABLE apple_messages_message_observations RENAME COLUMN sms_message_id TO apple_messages_message_id');
        DB::statement('ALTER TABLE apple_messages_message_observations RENAME COLUMN sms_source_set_id TO apple_messages_source_set_id');
    }

    private function renameColumnsToSms(): void
    {
        DB::statement('ALTER TABLE apple_messages_messages RENAME COLUMN apple_messages_conversation_id TO sms_conversation_id');
        DB::statement('ALTER TABLE apple_messages_attachments RENAME COLUMN apple_messages_message_id TO sms_message_id');
        DB::statement('ALTER TABLE apple_messages_conversation_participant RENAME COLUMN apple_messages_conversation_id TO sms_conversation_id');
        DB::statement('ALTER TABLE apple_messages_conversation_participant RENAME COLUMN apple_messages_participant_id TO sms_participant_id');
        DB::statement('ALTER TABLE apple_messages_message_observations RENAME COLUMN apple_messages_message_id TO sms_message_id');
        DB::statement('ALTER TABLE apple_messages_message_observations RENAME COLUMN apple_messages_source_set_id TO sms_source_set_id');
    }

    private function renameIndexesToAppleMessages(): void
    {
        DB::statement('ALTER TABLE apple_messages_source_sets RENAME INDEX sms_source_sets_source_key_unique TO apple_messages_source_sets_source_key_unique');
        DB::statement('ALTER TABLE apple_messages_conversations RENAME INDEX sms_conversations_conversation_key_unique TO apple_messages_conversations_conversation_key_unique');
        DB::statement('ALTER TABLE apple_messages_conversations RENAME INDEX sms_conversations_source_guid_index TO apple_messages_conversations_source_guid_index');
        DB::statement('ALTER TABLE apple_messages_conversations RENAME INDEX sms_conversations_chat_identifier_index TO apple_messages_conversations_chat_identifier_index');
        DB::statement('ALTER TABLE apple_messages_participants RENAME INDEX sms_participants_identifier_unique TO apple_messages_participants_identifier_unique');
        DB::statement('ALTER TABLE apple_messages_messages RENAME INDEX sms_messages_canonical_key_unique TO apple_messages_messages_canonical_key_unique');
        DB::statement('ALTER TABLE apple_messages_messages RENAME INDEX sms_messages_source_guid_index TO apple_messages_messages_source_guid_index');
        DB::statement('ALTER TABLE apple_messages_attachments RENAME INDEX sms_attachments_attachment_key_unique TO apple_messages_attachments_attachment_key_unique');
        DB::statement('ALTER TABLE apple_messages_attachments RENAME INDEX sms_attachments_source_guid_index TO apple_messages_attachments_source_guid_index');
    }

    private function renameIndexesToSms(): void
    {
        DB::statement('ALTER TABLE apple_messages_source_sets RENAME INDEX apple_messages_source_sets_source_key_unique TO sms_source_sets_source_key_unique');
        DB::statement('ALTER TABLE apple_messages_conversations RENAME INDEX apple_messages_conversations_conversation_key_unique TO sms_conversations_conversation_key_unique');
        DB::statement('ALTER TABLE apple_messages_conversations RENAME INDEX apple_messages_conversations_source_guid_index TO sms_conversations_source_guid_index');
        DB::statement('ALTER TABLE apple_messages_conversations RENAME INDEX apple_messages_conversations_chat_identifier_index TO sms_conversations_chat_identifier_index');
        DB::statement('ALTER TABLE apple_messages_participants RENAME INDEX apple_messages_participants_identifier_unique TO sms_participants_identifier_unique');
        DB::statement('ALTER TABLE apple_messages_messages RENAME INDEX apple_messages_messages_canonical_key_unique TO sms_messages_canonical_key_unique');
        DB::statement('ALTER TABLE apple_messages_messages RENAME INDEX apple_messages_messages_source_guid_index TO sms_messages_source_guid_index');
        DB::statement('ALTER TABLE apple_messages_attachments RENAME INDEX apple_messages_attachments_attachment_key_unique TO sms_attachments_attachment_key_unique');
        DB::statement('ALTER TABLE apple_messages_attachments RENAME INDEX apple_messages_attachments_source_guid_index TO sms_attachments_source_guid_index');
    }

    private function createAppleMessagesUniqueIndexes(): void
    {
        DB::statement(
            'ALTER TABLE apple_messages_conversation_participant ADD CONSTRAINT apple_messages_conversation_participant_unique UNIQUE (apple_messages_conversation_id, apple_messages_participant_id)'
        );
        DB::statement(
            'ALTER TABLE apple_messages_message_observations ADD CONSTRAINT apple_messages_message_observations_unique UNIQUE (apple_messages_message_id, apple_messages_source_set_id)'
        );
    }

    private function createSmsUniqueIndexes(): void
    {
        DB::statement(
            'ALTER TABLE sms_conversation_participant ADD CONSTRAINT sms_conversation_participant_unique UNIQUE (sms_conversation_id, sms_participant_id)'
        );
        DB::statement(
            'ALTER TABLE sms_message_observations ADD CONSTRAINT sms_message_observations_unique UNIQUE (sms_message_id, sms_source_set_id)'
        );
    }

    private function createAppleMessagesForeignKeys(): void
    {
        Schema::table('apple_messages_messages', function (Blueprint $table): void {
            $table->foreign('apple_messages_conversation_id', 'am_messages_conversation_fk')
                ->references('id')
                ->on('apple_messages_conversations')
                ->cascadeOnDelete();
            $table->foreign('sender_participant_id', 'am_messages_sender_participant_fk')
                ->references('id')
                ->on('apple_messages_participants')
                ->nullOnDelete();
        });

        Schema::table('apple_messages_attachments', function (Blueprint $table): void {
            $table->foreign('apple_messages_message_id', 'am_attachments_message_fk')
                ->references('id')
                ->on('apple_messages_messages')
                ->cascadeOnDelete();
        });

        Schema::table('apple_messages_conversation_participant', function (Blueprint $table): void {
            $table->foreign('apple_messages_conversation_id', 'am_conv_participant_conversation_fk')
                ->references('id')
                ->on('apple_messages_conversations')
                ->cascadeOnDelete();
            $table->foreign('apple_messages_participant_id', 'am_conv_participant_participant_fk')
                ->references('id')
                ->on('apple_messages_participants')
                ->cascadeOnDelete();
        });

        Schema::table('apple_messages_message_observations', function (Blueprint $table): void {
            $table->foreign('apple_messages_message_id', 'am_message_observation_message_fk')
                ->references('id')
                ->on('apple_messages_messages')
                ->cascadeOnDelete();
            $table->foreign('apple_messages_source_set_id', 'am_message_observation_source_set_fk')
                ->references('id')
                ->on('apple_messages_source_sets')
                ->cascadeOnDelete();
        });
    }

    private function createSmsForeignKeys(): void
    {
        Schema::table('sms_messages', function (Blueprint $table): void {
            $table->foreign('sms_conversation_id', 'sms_messages_conversation_fk')
                ->references('id')
                ->on('sms_conversations')
                ->cascadeOnDelete();
            $table->foreign('sender_participant_id', 'sms_messages_sender_participant_fk')
                ->references('id')
                ->on('sms_participants')
                ->nullOnDelete();
        });

        Schema::table('sms_attachments', function (Blueprint $table): void {
            $table->foreign('sms_message_id', 'sms_attachments_message_fk')
                ->references('id')
                ->on('sms_messages')
                ->cascadeOnDelete();
        });

        Schema::table('sms_conversation_participant', function (Blueprint $table): void {
            $table->foreign('sms_conversation_id', 'sms_conv_participant_conversation_fk')
                ->references('id')
                ->on('sms_conversations')
                ->cascadeOnDelete();
            $table->foreign('sms_participant_id', 'sms_conv_participant_participant_fk')
                ->references('id')
                ->on('sms_participants')
                ->cascadeOnDelete();
        });

        Schema::table('sms_message_observations', function (Blueprint $table): void {
            $table->foreign('sms_message_id', 'sms_message_observation_message_fk')
                ->references('id')
                ->on('sms_messages')
                ->cascadeOnDelete();
            $table->foreign('sms_source_set_id', 'sms_message_observation_source_set_fk')
                ->references('id')
                ->on('sms_source_sets')
                ->cascadeOnDelete();
        });
    }
};
