<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatgpt_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('archive_key')->unique();
            $table->string('source_locator');
            $table->string('source_file');
            $table->string('archive_label')->nullable();
            $table->timestamps();
        });

        Schema::create('chatgpt_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chatgpt_archive_id')->constrained('chatgpt_archives')->cascadeOnDelete();
            $table->string('conversation_id')->unique();
            $table->string('title')->nullable();
            $table->string('current_node')->nullable();
            $table->double('source_create_time')->nullable();
            $table->double('source_update_time')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('chatgpt_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chatgpt_conversation_id')->constrained('chatgpt_conversations')->cascadeOnDelete();
            $table->string('node_id');
            $table->string('parent_node_id')->nullable();
            $table->json('child_node_ids')->nullable();
            $table->json('raw_node')->nullable();
            $table->timestamps();

            $table->unique(['chatgpt_conversation_id', 'node_id'], 'chatgpt_nodes_conversation_node_unique');
        });

        Schema::create('chatgpt_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chatgpt_conversation_id')->constrained('chatgpt_conversations')->cascadeOnDelete();
            $table->foreignId('chatgpt_node_id')->constrained('chatgpt_nodes')->cascadeOnDelete();
            $table->string('message_id');
            $table->string('author_role')->nullable();
            $table->string('author_name')->nullable();
            $table->string('content_type')->nullable();
            $table->string('status')->nullable();
            $table->string('recipient')->nullable();
            $table->string('model_slug')->nullable();
            $table->double('source_create_time')->nullable();
            $table->double('source_update_time')->nullable();
            $table->boolean('end_turn')->nullable();
            $table->json('raw_message')->nullable();
            $table->timestamps();

            $table->unique(['chatgpt_conversation_id', 'message_id'], 'chatgpt_messages_conversation_message_unique');
        });

        Schema::create('chatgpt_message_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chatgpt_message_id')->constrained('chatgpt_messages')->cascadeOnDelete();
            $table->unsignedInteger('part_index');
            $table->string('part_type');
            $table->text('text_part')->nullable();
            $table->string('asset_pointer')->nullable();
            $table->json('raw_part')->nullable();
            $table->timestamps();

            $table->unique(['chatgpt_message_id', 'part_index'], 'chatgpt_message_parts_unique');
        });

        Schema::create('chatgpt_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chatgpt_message_id')->constrained('chatgpt_messages')->cascadeOnDelete();
            $table->string('asset_pointer');
            $table->string('asset_type')->nullable();
            $table->json('raw_asset')->nullable();
            $table->timestamps();

            $table->unique(['chatgpt_message_id', 'asset_pointer'], 'chatgpt_assets_message_pointer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatgpt_assets');
        Schema::dropIfExists('chatgpt_message_parts');
        Schema::dropIfExists('chatgpt_messages');
        Schema::dropIfExists('chatgpt_nodes');
        Schema::dropIfExists('chatgpt_conversations');
        Schema::dropIfExists('chatgpt_archives');
    }
};
