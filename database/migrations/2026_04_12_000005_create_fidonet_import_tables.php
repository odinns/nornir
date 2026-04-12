<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fidonet_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('source_locator')->unique();
            $table->string('access_mode');
            $table->string('driver');
            $table->string('database_name');
            $table->string('host')->nullable();
            $table->string('port')->nullable();
            $table->string('username')->nullable();
            $table->json('scope_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('fidonet_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fidonet_source_id')->constrained('fidonet_sources')->cascadeOnDelete();
            $table->string('area_code')->unique();
            $table->string('area_name');
            $table->string('source_type')->nullable();
            $table->string('area_type')->nullable();
            $table->timestamps();
        });

        Schema::create('fidonet_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fidonet_source_id')->constrained('fidonet_sources')->cascadeOnDelete();
            $table->string('canonical_message_id')->unique();
            $table->string('area_code')->index();
            $table->unsignedBigInteger('source_message_row_id')->nullable();
            $table->unsignedInteger('source_msgno');
            $table->string('subject')->nullable();
            $table->string('from_name');
            $table->string('from_address')->nullable();
            $table->string('to_name');
            $table->string('to_address')->nullable();
            $table->unsignedInteger('reply_to_msgno')->nullable();
            $table->string('reply_to_external_id')->nullable();
            $table->unsignedInteger('reply1st_msgno')->nullable();
            $table->unsignedInteger('replynext_msgno')->nullable();
            $table->string('source_thread_key')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->json('raw_metadata_json')->nullable();
            $table->timestamps();
        });

        Schema::create('fidonet_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('participant_key', 40)->unique();
            $table->string('display_name');
            $table->string('address')->nullable();
            $table->string('normalized_name')->nullable();
            $table->string('normalized_address')->nullable();
            $table->boolean('is_odinn_candidate')->default(false);
            $table->timestamps();
        });

        Schema::create('fidonet_message_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('canonical_message_id');
            $table->foreignId('fidonet_participant_id')->constrained('fidonet_participants')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(
                ['canonical_message_id', 'fidonet_participant_id', 'role'],
                'fidonet_message_participants_unique'
            );
        });

        Schema::create('fidonet_threads', function (Blueprint $table): void {
            $table->id();
            $table->string('area_code')->index();
            $table->string('derived_thread_key')->unique();
            $table->string('source_method');
            $table->unsignedInteger('message_count');
            $table->boolean('is_synthetic')->default(false);
            $table->string('confidence')->nullable();
            $table->timestamps();
        });

        Schema::create('fidonet_thread_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fidonet_thread_id')->constrained('fidonet_threads')->cascadeOnDelete();
            $table->string('canonical_message_id');
            $table->unsignedInteger('thread_order');
            $table->timestamps();

            $table->unique(['fidonet_thread_id', 'canonical_message_id'], 'fidonet_thread_messages_unique');
        });

        Schema::create('fidonet_message_cleanup', function (Blueprint $table): void {
            $table->id();
            $table->string('canonical_message_id')->unique();
            $table->longText('cleaned_authored_text')->nullable();
            $table->longText('quote_text')->nullable();
            $table->longText('metadata_text')->nullable();
            $table->longText('embedded_text')->nullable();
            $table->string('cleanup_version');
            $table->boolean('is_test_like')->default(false);
            $table->text('cleanup_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fidonet_message_cleanup');
        Schema::dropIfExists('fidonet_thread_messages');
        Schema::dropIfExists('fidonet_threads');
        Schema::dropIfExists('fidonet_message_participants');
        Schema::dropIfExists('fidonet_participants');
        Schema::dropIfExists('fidonet_messages');
        Schema::dropIfExists('fidonet_areas');
        Schema::dropIfExists('fidonet_sources');
    }
};
