<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twitter_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('archive_key')->unique();
            $table->string('source_locator')->unique();
            $table->string('access_mode');
            $table->string('account_id')->nullable()->index();
            $table->string('username')->nullable();
            $table->string('archive_generated_at_source')->nullable();
            $table->timestamp('archive_generated_at')->nullable();
            $table->json('raw_manifest')->nullable();
            $table->timestamps();
        });

        Schema::create('twitter_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('twitter_archive_id')->constrained('twitter_archives')->cascadeOnDelete();
            $table->string('account_id')->index();
            $table->string('username')->nullable();
            $table->string('display_name')->nullable();
            $table->string('created_at_source')->nullable();
            $table->timestamp('account_created_at')->nullable();
            $table->json('raw_account')->nullable();
            $table->timestamps();

            $table->unique('twitter_archive_id');
        });

        Schema::create('twitter_profile_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('twitter_archive_id')->constrained('twitter_archives')->cascadeOnDelete();
            $table->string('account_id')->nullable()->index();
            $table->string('screen_name')->nullable();
            $table->string('display_name')->nullable();
            $table->longText('bio')->nullable();
            $table->string('location')->nullable();
            $table->string('website_url')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('header_path')->nullable();
            $table->boolean('is_verified')->nullable();
            $table->boolean('is_verified_organization')->nullable();
            $table->json('raw_profile')->nullable();
            $table->timestamps();

            $table->unique('twitter_archive_id');
        });

        Schema::create('twitter_screen_name_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('twitter_archive_id')->constrained('twitter_archives')->cascadeOnDelete();
            $table->string('account_id')->nullable()->index();
            $table->string('screen_name');
            $table->string('changed_at_source')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->json('raw_change')->nullable();
            $table->timestamps();

            $table->unique(
                ['twitter_archive_id', 'screen_name', 'changed_at_source'],
                'twitter_screen_name_changes_archive_name_changed_at_unique'
            );
        });

        Schema::create('twitter_tweets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_twitter_archive_id')->nullable()->constrained('twitter_archives')->nullOnDelete();
            $table->string('account_id')->nullable()->index();
            $table->string('tweet_id')->unique();
            $table->string('source_surface');
            $table->string('created_at_source')->nullable();
            $table->timestamp('tweeted_at')->nullable()->index();
            $table->longText('full_text')->nullable();
            $table->string('source_client')->nullable();
            $table->string('lang')->nullable();
            $table->string('conversation_id')->nullable()->index();
            $table->string('in_reply_to_tweet_id')->nullable()->index();
            $table->string('in_reply_to_user_id')->nullable();
            $table->unsignedInteger('retweet_count')->nullable();
            $table->unsignedInteger('reply_count')->nullable();
            $table->unsignedInteger('like_count')->nullable();
            $table->unsignedInteger('quote_count')->nullable();
            $table->unsignedInteger('bookmark_count')->nullable();
            $table->json('raw_tweet')->nullable();
            $table->timestamps();
        });

        Schema::create('twitter_note_tweets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_seen_twitter_archive_id')->nullable()->constrained('twitter_archives')->nullOnDelete();
            $table->string('account_id')->nullable()->index();
            $table->string('note_tweet_id')->unique();
            $table->string('created_at_source')->nullable();
            $table->timestamp('tweeted_at')->nullable()->index();
            $table->longText('full_text')->nullable();
            $table->json('raw_note_tweet')->nullable();
            $table->timestamps();
        });

        Schema::create('twitter_media_refs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('twitter_archive_id')->nullable()->constrained('twitter_archives')->nullOnDelete();
            $table->string('account_id')->nullable()->index();
            $table->string('media_key')->unique();
            $table->string('owner_type');
            $table->string('owner_id');
            $table->string('source_surface');
            $table->string('relative_path')->nullable();
            $table->string('source_url')->nullable();
            $table->string('media_type')->nullable();
            $table->json('raw_media')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twitter_media_refs');
        Schema::dropIfExists('twitter_note_tweets');
        Schema::dropIfExists('twitter_tweets');
        Schema::dropIfExists('twitter_screen_name_changes');
        Schema::dropIfExists('twitter_profile_snapshots');
        Schema::dropIfExists('twitter_accounts');
        Schema::dropIfExists('twitter_archives');
    }
};
