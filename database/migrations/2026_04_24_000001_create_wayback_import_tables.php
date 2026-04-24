<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wayback_scopes', function (Blueprint $table): void {
            $table->id();
            $table->string('scope');
            $table->string('match_mode', 16);
            $table->string('host')->nullable();
            $table->text('path')->nullable();
            $table->string('from_timestamp', 14)->nullable();
            $table->string('to_timestamp', 14)->nullable();
            $table->json('filter_policy');
            $table->string('source_key', 64)->unique();
            $table->timestamps();
        });

        Schema::create('wayback_captures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wayback_scope_id')->constrained('wayback_scopes')->cascadeOnDelete();
            $table->string('timestamp', 14);
            $table->dateTime('captured_at');
            $table->text('original_url');
            $table->string('original_url_hash', 64);
            $table->text('replay_url');
            $table->json('cdx_fields');
            $table->string('page_key', 64);
            $table->string('digest')->nullable();
            $table->string('verdict', 32);
            $table->string('reject_reason')->nullable();
            $table->longText('raw_replay_html')->nullable();
            $table->longText('extracted_authored_text')->nullable();
            $table->text('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('retrieval_metadata');
            $table->text('screenshot_path')->nullable();
            $table->string('screenshot_hash', 64)->nullable();
            $table->text('mirror_path')->nullable();
            $table->json('raw_cdx_json');
            $table->string('biographical_surface', 64)->nullable();
            $table->date('timeline_anchor_date')->nullable();
            $table->text('evidence_summary')->nullable();
            $table->timestamps();

            $table->unique(['wayback_scope_id', 'timestamp', 'original_url_hash'], 'wayback_captures_identity_unique');
            $table->index(['wayback_scope_id', 'verdict']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wayback_captures');
        Schema::dropIfExists('wayback_scopes');
    }
};
