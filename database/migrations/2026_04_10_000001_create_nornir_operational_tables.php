<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_records', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type');
            $table->string('access_mode');
            $table->string('source_locator');
            $table->json('scope_snapshot');
            $table->json('importer_options')->nullable();
            $table->timestamps();
        });

        Schema::create('runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->string('subsystem');
            $table->string('operation');
            $table->string('status');
            $table->json('input_scope');
            $table->string('idempotency_key');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestamps();

            $table->unique(['subsystem', 'operation', 'idempotency_key'], 'runs_logical_identity_unique');
        });

        Schema::create('run_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->string('event');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('run_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->string('artifact_kind');
            $table->string('locator');
            $table->string('classification');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'artifact_kind', 'locator', 'classification'], 'run_artifacts_unique');
        });

        Schema::create('provenance_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->string('output_target');
            $table->string('claim_key');
            $table->string('evidence_type');
            $table->string('evidence_ref');
            $table->string('dedupe_key', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'dedupe_key'], 'provenance_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provenance_links');
        Schema::dropIfExists('run_artifacts');
        Schema::dropIfExists('run_events');
        Schema::dropIfExists('runs');
        Schema::dropIfExists('intake_records');
    }
};
