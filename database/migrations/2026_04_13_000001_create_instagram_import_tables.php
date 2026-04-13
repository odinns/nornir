<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('account_key', 40)->unique();
            $table->string('username')->unique();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('access_mode', 32)->default('local-path');
            $table->timestamps();
        });

        Schema::create('instagram_profile_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instagram_account_id')->constrained('instagram_accounts')->cascadeOnDelete();
            $table->string('snapshot_key', 40)->unique();
            $table->string('username')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->dateTime('snapshotted_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('instagram_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instagram_account_id')->constrained('instagram_accounts')->cascadeOnDelete();
            $table->string('post_key', 40)->unique();
            $table->text('caption')->nullable();
            $table->unsignedBigInteger('post_timestamp')->nullable();
            $table->unsignedTinyInteger('media_count')->default(1);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('instagram_media_refs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instagram_account_id')->constrained('instagram_accounts')->cascadeOnDelete();
            $table->foreignId('instagram_post_id')->nullable()->constrained('instagram_posts')->nullOnDelete();
            $table->string('media_ref_key', 40)->unique();
            $table->string('uri', 1000);
            $table->string('media_type', 32);
            $table->unsignedBigInteger('creation_timestamp')->nullable();
            $table->text('title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_media_refs');
        Schema::dropIfExists('instagram_posts');
        Schema::dropIfExists('instagram_profile_snapshots');
        Schema::dropIfExists('instagram_accounts');
    }
};
