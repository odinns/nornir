<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fidonet_sources', function (Blueprint $table): void {
            $table->string('scope_hash', 40)->nullable()->after('source_locator');
            $table->dropUnique('fidonet_sources_source_locator_unique');
            $table->unique(['source_locator', 'scope_hash'], 'fidonet_sources_locator_scope_unique');
        });

        DB::table('fidonet_sources')
            ->orderBy('id')
            ->get(['id', 'scope_snapshot'])
            ->each(function (object $row): void {
                $scopeSnapshot = $row->scope_snapshot;
                $normalizedScope = is_string($scopeSnapshot)
                    ? $scopeSnapshot
                    : json_encode($scopeSnapshot ?? [], JSON_THROW_ON_ERROR);

                DB::table('fidonet_sources')
                    ->where('id', $row->id)
                    ->update(['scope_hash' => sha1($normalizedScope)]);
            });

        Schema::table('fidonet_sources', function (Blueprint $table): void {
            $table->string('scope_hash', 40)->nullable(false)->change();
        });

        Schema::create('fidonet_area_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fidonet_source_id')->constrained('fidonet_sources')->cascadeOnDelete();
            $table->string('area_code');
            $table->timestamps();

            $table->unique(['fidonet_source_id', 'area_code'], 'fidonet_area_observations_unique');
        });

        Schema::create('fidonet_thread_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fidonet_source_id')->constrained('fidonet_sources')->cascadeOnDelete();
            $table->foreignId('fidonet_thread_id')->constrained('fidonet_threads')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fidonet_source_id', 'fidonet_thread_id'], 'fidonet_thread_observations_unique');
        });

        Schema::create('fidonet_message_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fidonet_source_id')->constrained('fidonet_sources')->cascadeOnDelete();
            $table->string('canonical_message_id');
            $table->timestamps();

            $table->unique(['fidonet_source_id', 'canonical_message_id'], 'fidonet_message_observations_unique');
        });

        DB::table('fidonet_areas')
            ->get(['fidonet_source_id', 'area_code'])
            ->each(function (object $row): void {
                DB::table('fidonet_area_observations')->updateOrInsert(
                    [
                        'fidonet_source_id' => $row->fidonet_source_id,
                        'area_code' => $row->area_code,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });

        DB::table('fidonet_threads')
            ->join('fidonet_messages', 'fidonet_messages.area_code', '=', 'fidonet_threads.area_code')
            ->join('fidonet_thread_messages', 'fidonet_thread_messages.fidonet_thread_id', '=', 'fidonet_threads.id')
            ->select('fidonet_messages.fidonet_source_id', 'fidonet_threads.id as fidonet_thread_id')
            ->distinct()
            ->get()
            ->each(function (object $row): void {
                DB::table('fidonet_thread_observations')->updateOrInsert(
                    [
                        'fidonet_source_id' => $row->fidonet_source_id,
                        'fidonet_thread_id' => $row->fidonet_thread_id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });

        DB::table('fidonet_messages')
            ->get(['fidonet_source_id', 'canonical_message_id'])
            ->each(function (object $row): void {
                DB::table('fidonet_message_observations')->updateOrInsert(
                    [
                        'fidonet_source_id' => $row->fidonet_source_id,
                        'canonical_message_id' => $row->canonical_message_id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('fidonet_message_observations');
        Schema::dropIfExists('fidonet_thread_observations');
        Schema::dropIfExists('fidonet_area_observations');

        Schema::table('fidonet_sources', function (Blueprint $table): void {
            $table->dropUnique('fidonet_sources_locator_scope_unique');
            $table->dropColumn('scope_hash');
            $table->unique('source_locator');
        });
    }
};
