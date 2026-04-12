<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('archive_key')->unique();
            $table->string('source_locator')->unique();
            $table->string('access_mode');
            $table->timestamps();
        });

        Schema::create('linkedin_people', function (Blueprint $table): void {
            $table->id();
            $table->string('person_key')->unique();
            $table->string('display_name');
            $table->string('normalized_name')->nullable()->index();
            $table->string('profile_url')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('linkedin_profile_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('linkedin_archive_id')->constrained('linkedin_archives')->cascadeOnDelete();
            $table->foreignId('linkedin_person_id')->nullable()->constrained('linkedin_people')->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('headline')->nullable();
            $table->longText('summary')->nullable();
            $table->string('industry')->nullable();
            $table->string('address')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('geo_location')->nullable();
            $table->string('birth_date_source')->nullable();
            $table->date('birth_date')->nullable();
            $table->json('emails_json')->nullable();
            $table->json('phone_numbers_json')->nullable();
            $table->json('whatsapp_numbers_json')->nullable();
            $table->string('registration_at_source')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->string('registration_ip')->nullable();
            $table->json('raw_profile')->nullable();
            $table->timestamps();

            $table->unique('linkedin_archive_id');
        });

        Schema::create('linkedin_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_positions_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('company_name')->nullable()->index();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->string('location')->nullable();
            $table->string('started_on_source')->nullable();
            $table->timestamp('started_on')->nullable();
            $table->string('finished_on_source')->nullable();
            $table->timestamp('finished_on')->nullable();
            $table->json('raw_position')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_education_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_education_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('school_name')->nullable();
            $table->string('degree_name')->nullable();
            $table->string('start_date_source')->nullable();
            $table->timestamp('started_on')->nullable();
            $table->string('end_date_source')->nullable();
            $table->timestamp('finished_on')->nullable();
            $table->longText('notes')->nullable();
            $table->longText('activities')->nullable();
            $table->json('raw_record')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_projects_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->string('url')->nullable();
            $table->string('started_on_source')->nullable();
            $table->timestamp('started_on')->nullable();
            $table->string('finished_on_source')->nullable();
            $table->timestamp('finished_on')->nullable();
            $table->json('raw_project')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_skills_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('skill_name')->unique();
            $table->timestamps();
        });

        Schema::create('linkedin_languages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_languages_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('name');
            $table->string('proficiency')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_connections_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->foreignId('linkedin_person_id')->nullable()->constrained('linkedin_people')->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('email_address')->nullable();
            $table->string('company')->nullable();
            $table->string('position')->nullable();
            $table->string('connected_on_source')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->json('raw_connection')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_invitations_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->foreignId('sender_linkedin_person_id')->nullable();
            $table->foreign('sender_linkedin_person_id', 'li_invitations_sender_person_fk')
                ->references('id')
                ->on('linkedin_people')
                ->nullOnDelete();
            $table->foreignId('recipient_linkedin_person_id')->nullable();
            $table->foreign('recipient_linkedin_person_id', 'li_invitations_recipient_person_fk')
                ->references('id')
                ->on('linkedin_people')
                ->nullOnDelete();
            $table->string('direction')->nullable()->index();
            $table->string('sent_at_source')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->longText('message')->nullable();
            $table->string('inviter_profile_url')->nullable();
            $table->string('invitee_profile_url')->nullable();
            $table->json('raw_invitation')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_recommendations_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('direction')->index();
            $table->foreignId('counterpart_linkedin_person_id')->nullable();
            $table->foreign('counterpart_linkedin_person_id', 'li_recommendations_counterpart_person_fk')
                ->references('id')
                ->on('linkedin_people')
                ->nullOnDelete();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->longText('text')->nullable();
            $table->string('created_at_source')->nullable();
            $table->timestamp('recommended_at')->nullable();
            $table->string('status')->nullable();
            $table->json('raw_recommendation')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_endorsements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_endorsements_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('direction')->index();
            $table->foreignId('counterpart_linkedin_person_id')->nullable();
            $table->foreign('counterpart_linkedin_person_id', 'li_endorsements_counterpart_person_fk')
                ->references('id')
                ->on('linkedin_people')
                ->nullOnDelete();
            $table->string('skill_name')->index();
            $table->string('endorsed_at_source')->nullable();
            $table->timestamp('endorsed_at')->nullable();
            $table->string('status')->nullable();
            $table->string('counterpart_public_url')->nullable();
            $table->json('raw_endorsement')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_conversations_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('conversation_key')->unique();
            $table->string('source_conversation_id')->index();
            $table->string('title')->nullable();
            $table->string('folder')->nullable()->index();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('linkedin_conversation_id')->constrained('linkedin_conversations')->cascadeOnDelete();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_messages_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->foreignId('sender_linkedin_person_id')->nullable();
            $table->foreign('sender_linkedin_person_id', 'li_messages_sender_person_fk')
                ->references('id')
                ->on('linkedin_people')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('sent_at_source')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->string('subject')->nullable();
            $table->longText('content')->nullable();
            $table->longText('to_display')->nullable();
            $table->longText('recipient_profile_urls')->nullable();
            $table->string('folder')->nullable()->index();
            $table->json('raw_message')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('linkedin_message_id')->constrained('linkedin_messages')->cascadeOnDelete();
            $table->string('attachment_key')->unique();
            $table->json('attachment_urls_json');
            $table->timestamps();
        });

        Schema::create('linkedin_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_shares_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('shared_at_source')->nullable();
            $table->timestamp('shared_at')->nullable()->index();
            $table->string('share_link')->nullable()->index();
            $table->longText('commentary')->nullable();
            $table->string('shared_url')->nullable();
            $table->string('media_url')->nullable();
            $table->string('visibility')->nullable();
            $table->json('raw_share')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_comments_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('commented_at_source')->nullable();
            $table->timestamp('commented_at')->nullable()->index();
            $table->string('link')->nullable()->index();
            $table->longText('message')->nullable();
            $table->json('raw_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_reactions_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('reacted_at_source')->nullable();
            $table->timestamp('reacted_at')->nullable()->index();
            $table->string('reaction_type')->nullable();
            $table->string('link')->nullable()->index();
            $table->json('raw_reaction')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_rich_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_linkedin_archive_id')->nullable();
            $table->foreign('first_seen_linkedin_archive_id', 'li_rich_media_first_seen_archive_fk')
                ->references('id')
                ->on('linkedin_archives')
                ->nullOnDelete();
            $table->string('canonical_key')->unique();
            $table->string('observed_at_source')->nullable();
            $table->timestamp('observed_at')->nullable()->index();
            $table->longText('media_description')->nullable();
            $table->string('media_link')->nullable();
            $table->json('raw_media')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_rich_media');
        Schema::dropIfExists('linkedin_reactions');
        Schema::dropIfExists('linkedin_comments');
        Schema::dropIfExists('linkedin_shares');
        Schema::dropIfExists('linkedin_message_attachments');
        Schema::dropIfExists('linkedin_messages');
        Schema::dropIfExists('linkedin_conversations');
        Schema::dropIfExists('linkedin_endorsements');
        Schema::dropIfExists('linkedin_recommendations');
        Schema::dropIfExists('linkedin_invitations');
        Schema::dropIfExists('linkedin_connections');
        Schema::dropIfExists('linkedin_languages');
        Schema::dropIfExists('linkedin_skills');
        Schema::dropIfExists('linkedin_projects');
        Schema::dropIfExists('linkedin_education_records');
        Schema::dropIfExists('linkedin_positions');
        Schema::dropIfExists('linkedin_profile_snapshots');
        Schema::dropIfExists('linkedin_people');
        Schema::dropIfExists('linkedin_archives');
    }
};
