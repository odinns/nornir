<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key')->unique();
            $table->string('source_locator')->unique();
            $table->string('access_mode');
            $table->timestamps();
        });

        Schema::create('facebook_people', function (Blueprint $table): void {
            $table->id();
            $table->string('person_key')->unique();
            $table->string('display_name');
            $table->string('normalized_name')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('facebook_profile_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->foreignId('facebook_person_id')->nullable()->constrained('facebook_people')->nullOnDelete();
            $table->string('full_name')->nullable();
            $table->json('emails_json')->nullable();
            $table->string('current_city')->nullable();
            $table->string('hometown')->nullable();
            $table->json('raw_profile')->nullable();
            $table->timestamps();

            $table->unique('facebook_archive_id');
        });

        Schema::create('facebook_social_edges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->foreignId('facebook_person_id')->constrained('facebook_people')->cascadeOnDelete();
            $table->string('edge_type');
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['facebook_archive_id', 'facebook_person_id', 'edge_type'],
                'facebook_social_edges_archive_person_edge_type_unique'
            );
        });

        Schema::create('facebook_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->string('thread_key')->unique();
            $table->string('thread_uid')->nullable()->index();
            $table->string('category');
            $table->string('title')->nullable();
            $table->boolean('is_still_participant')->default(false);
            $table->string('thread_path')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->json('raw_thread')->nullable();
            $table->timestamps();
        });

        Schema::create('facebook_thread_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_thread_id')->constrained('facebook_threads')->cascadeOnDelete();
            $table->foreignId('facebook_person_id')->constrained('facebook_people')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['facebook_thread_id', 'facebook_person_id'],
                'facebook_thread_participants_thread_person_unique'
            );
        });

        Schema::create('facebook_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->string('canonical_key')->unique();
            $table->unsignedBigInteger('published_timestamp')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->json('raw_post')->nullable();
            $table->timestamps();
        });

        Schema::create('facebook_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->string('canonical_key')->unique();
            $table->unsignedBigInteger('published_timestamp')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->json('raw_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('facebook_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->foreignId('facebook_person_id')->nullable()->constrained('facebook_people')->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->unsignedBigInteger('published_timestamp')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->string('title')->nullable();
            $table->string('reaction');
            $table->json('raw_reaction')->nullable();
            $table->timestamps();
        });

        Schema::create('facebook_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_thread_id')->constrained('facebook_threads')->cascadeOnDelete();
            $table->foreignId('sender_facebook_person_id')->nullable()->constrained('facebook_people')->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->unsignedBigInteger('timestamp_ms')->index();
            $table->timestamp('sent_at')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_unsent')->default(false);
            $table->json('raw_message')->nullable();
            $table->timestamps();
        });

        Schema::create('facebook_message_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_message_id')->constrained('facebook_messages')->cascadeOnDelete();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['facebook_message_id', 'facebook_archive_id'],
                'facebook_message_observations_message_archive_unique'
            );
        });

        Schema::create('facebook_post_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_post_id')->constrained('facebook_posts')->cascadeOnDelete();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['facebook_post_id', 'facebook_archive_id'],
                'facebook_post_observations_post_archive_unique'
            );
        });

        Schema::create('facebook_comment_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_comment_id')->constrained('facebook_comments')->cascadeOnDelete();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['facebook_comment_id', 'facebook_archive_id'],
                'facebook_comment_observations_comment_archive_unique'
            );
        });

        Schema::create('facebook_reaction_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_reaction_id')->constrained('facebook_reactions')->cascadeOnDelete();
            $table->foreignId('facebook_archive_id')->constrained('facebook_archives')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['facebook_reaction_id', 'facebook_archive_id'],
                'facebook_reaction_observations_reaction_archive_unique'
            );
        });

        Schema::create('facebook_message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_message_id')->constrained('facebook_messages')->cascadeOnDelete();
            $table->foreignId('facebook_person_id')->nullable()->constrained('facebook_people')->nullOnDelete();
            $table->string('reaction_key')->unique();
            $table->string('reaction');
            $table->timestamps();
        });

        Schema::create('facebook_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_message_id')->nullable()->constrained('facebook_messages')->cascadeOnDelete();
            $table->foreignId('facebook_post_id')->nullable()->constrained('facebook_posts')->cascadeOnDelete();
            $table->string('attachment_key')->unique();
            $table->string('source_context');
            $table->string('attachment_type');
            $table->string('relative_path')->nullable();
            $table->string('source_uri')->nullable();
            $table->unsignedBigInteger('created_timestamp')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->json('raw_attachment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_attachments');
        Schema::dropIfExists('facebook_message_reactions');
        Schema::dropIfExists('facebook_reaction_observations');
        Schema::dropIfExists('facebook_comment_observations');
        Schema::dropIfExists('facebook_post_observations');
        Schema::dropIfExists('facebook_message_observations');
        Schema::dropIfExists('facebook_messages');
        Schema::dropIfExists('facebook_reactions');
        Schema::dropIfExists('facebook_comments');
        Schema::dropIfExists('facebook_posts');
        Schema::dropIfExists('facebook_thread_participants');
        Schema::dropIfExists('facebook_threads');
        Schema::dropIfExists('facebook_social_edges');
        Schema::dropIfExists('facebook_profile_snapshots');
        Schema::dropIfExists('facebook_people');
        Schema::dropIfExists('facebook_archives');
    }
};
