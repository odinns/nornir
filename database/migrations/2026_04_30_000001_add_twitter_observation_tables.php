<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twitter_tweet_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('twitter_tweet_id')->constrained('twitter_tweets')->cascadeOnDelete();
            $table->foreignId('twitter_archive_id')->constrained('twitter_archives')->cascadeOnDelete();
            $table->string('source')->default('import');
            $table->timestamps();

            $table->unique(['twitter_tweet_id', 'twitter_archive_id'], 'twitter_tweet_observations_unique');
        });

        Schema::create('twitter_note_tweet_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('twitter_note_tweet_id')->constrained('twitter_note_tweets')->cascadeOnDelete();
            $table->foreignId('twitter_archive_id')->constrained('twitter_archives')->cascadeOnDelete();
            $table->string('source')->default('import');
            $table->timestamps();

            $table->unique(['twitter_note_tweet_id', 'twitter_archive_id'], 'twitter_note_tweet_observations_unique');
        });

        Schema::create('twitter_media_ref_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('twitter_media_ref_id')->constrained('twitter_media_refs')->cascadeOnDelete();
            $table->foreignId('twitter_archive_id')->constrained('twitter_archives')->cascadeOnDelete();
            $table->string('source')->default('import');
            $table->timestamps();

            $table->unique(['twitter_media_ref_id', 'twitter_archive_id'], 'twitter_media_ref_observations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twitter_media_ref_observations');
        Schema::dropIfExists('twitter_note_tweet_observations');
        Schema::dropIfExists('twitter_tweet_observations');
    }
};
