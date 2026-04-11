<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatgpt_source_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key')->unique();
            $table->string('source_locator');
            $table->string('access_mode');
            $table->timestamps();
        });

        Schema::table('chatgpt_archives', function (Blueprint $table): void {
            $table->unsignedBigInteger('chatgpt_source_set_id')->nullable()->after('id');
            $table->foreign('chatgpt_source_set_id', 'chatgpt_archives_source_set_fk')
                ->references('id')
                ->on('chatgpt_source_sets')
                ->cascadeOnDelete();
            $table->unique(['chatgpt_source_set_id', 'source_file'], 'chatgpt_archives_source_set_file_unique');
        });

        Schema::create('chatgpt_conversation_observations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('chatgpt_conversation_id');
            $table->unsignedBigInteger('chatgpt_source_set_id');
            $table->unsignedBigInteger('chatgpt_archive_id')->nullable();
            $table->timestamps();

            $table->foreign('chatgpt_conversation_id', 'chatgpt_conv_obs_conv_fk')
                ->references('id')
                ->on('chatgpt_conversations')
                ->cascadeOnDelete();
            $table->foreign('chatgpt_source_set_id', 'chatgpt_conv_obs_source_set_fk')
                ->references('id')
                ->on('chatgpt_source_sets')
                ->cascadeOnDelete();
            $table->foreign('chatgpt_archive_id', 'chatgpt_conv_obs_archive_fk')
                ->references('id')
                ->on('chatgpt_archives')
                ->nullOnDelete();

            $table->unique(
                ['chatgpt_conversation_id', 'chatgpt_source_set_id'],
                'chatgpt_conversation_observations_unique'
            );
        });

        Schema::create('chatgpt_message_observations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('chatgpt_message_id');
            $table->unsignedBigInteger('chatgpt_source_set_id');
            $table->unsignedBigInteger('chatgpt_archive_id')->nullable();
            $table->timestamps();

            $table->foreign('chatgpt_message_id', 'chatgpt_msg_obs_msg_fk')
                ->references('id')
                ->on('chatgpt_messages')
                ->cascadeOnDelete();
            $table->foreign('chatgpt_source_set_id', 'chatgpt_msg_obs_source_set_fk')
                ->references('id')
                ->on('chatgpt_source_sets')
                ->cascadeOnDelete();
            $table->foreign('chatgpt_archive_id', 'chatgpt_msg_obs_archive_fk')
                ->references('id')
                ->on('chatgpt_archives')
                ->nullOnDelete();

            $table->unique(
                ['chatgpt_message_id', 'chatgpt_source_set_id'],
                'chatgpt_message_observations_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatgpt_message_observations');
        Schema::dropIfExists('chatgpt_conversation_observations');

        Schema::table('chatgpt_archives', function (Blueprint $table): void {
            $table->dropUnique('chatgpt_archives_source_set_file_unique');
            $table->dropForeign('chatgpt_archives_source_set_fk');
            $table->dropColumn('chatgpt_source_set_id');
        });

        Schema::dropIfExists('chatgpt_source_sets');
    }
};
