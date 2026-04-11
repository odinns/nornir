<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_source_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key')->unique();
            $table->string('source_locator');
            $table->string('access_mode');
            $table->string('attachments_root')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('conversation_key')->unique();
            $table->string('source_guid')->nullable()->index();
            $table->string('chat_identifier')->nullable()->index();
            $table->string('display_name')->nullable();
            $table->string('room_name')->nullable();
            $table->string('service')->nullable();
            $table->unsignedTinyInteger('style')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->json('raw_chat')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('service')->nullable();
            $table->string('uncanonicalized_identifier')->nullable();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_conversation_id')->constrained('sms_conversations')->cascadeOnDelete();
            $table->foreignId('sender_participant_id')->nullable()->constrained('sms_participants')->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('source_guid')->nullable()->index();
            $table->unsignedBigInteger('source_row_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->boolean('from_me')->default(false);
            $table->string('service')->nullable();
            $table->longText('text_body')->nullable();
            $table->boolean('is_delivered')->default(false);
            $table->boolean('is_read')->default(false);
            $table->boolean('is_sent')->default(false);
            $table->unsignedTinyInteger('item_type')->default(0);
            $table->string('group_title')->nullable();
            $table->unsignedTinyInteger('group_action_type')->nullable();
            $table->string('reaction_to_guid')->nullable();
            $table->unsignedSmallInteger('reaction_type')->nullable();
            $table->json('raw_message')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_message_id')->constrained('sms_messages')->cascadeOnDelete();
            $table->string('attachment_key')->unique();
            $table->string('source_guid')->nullable()->index();
            $table->string('relative_path')->nullable();
            $table->string('source_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('transfer_name')->nullable();
            $table->unsignedBigInteger('total_bytes')->nullable();
            $table->json('raw_attachment')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_conversation_participant', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_conversation_id')->constrained('sms_conversations')->cascadeOnDelete();
            $table->foreignId('sms_participant_id')->constrained('sms_participants')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['sms_conversation_id', 'sms_participant_id'],
                'sms_conversation_participant_unique'
            );
        });

        Schema::create('sms_message_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_message_id')->constrained('sms_messages')->cascadeOnDelete();
            $table->foreignId('sms_source_set_id')->constrained('sms_source_sets')->cascadeOnDelete();
            $table->unsignedBigInteger('source_message_row_id')->nullable();
            $table->timestamps();

            $table->unique(['sms_message_id', 'sms_source_set_id'], 'sms_message_observations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_message_observations');
        Schema::dropIfExists('sms_conversation_participant');
        Schema::dropIfExists('sms_attachments');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_participants');
        Schema::dropIfExists('sms_conversations');
        Schema::dropIfExists('sms_source_sets');
    }
};
