<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_health_source_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key')->unique();
            $table->string('source_locator');
            $table->string('access_mode');
            $table->string('export_xml_path');
            $table->timestamps();
        });

        Schema::create('apple_health_records', function (Blueprint $table): void {
            $table->id();
            $table->string('canonical_key')->unique();
            $table->string('record_type')->index();
            $table->string('source_name')->nullable()->index();
            $table->string('source_version')->nullable();
            $table->string('unit')->nullable();
            $table->string('value')->nullable();
            $table->timestamp('creation_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->json('raw_record')->nullable();
            $table->timestamps();
        });

        Schema::create('apple_health_workouts', function (Blueprint $table): void {
            $table->id();
            $table->string('canonical_key')->unique();
            $table->string('workout_activity_type')->index();
            $table->string('source_name')->nullable()->index();
            $table->string('source_version')->nullable();
            $table->decimal('duration', 12, 4)->nullable();
            $table->string('duration_unit')->nullable();
            $table->string('total_energy_burned')->nullable();
            $table->string('total_energy_burned_unit')->nullable();
            $table->string('total_distance')->nullable();
            $table->string('total_distance_unit')->nullable();
            $table->timestamp('creation_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->json('raw_workout')->nullable();
            $table->timestamps();
        });

        Schema::create('apple_health_record_observations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apple_health_record_id');
            $table->unsignedBigInteger('apple_health_source_set_id');
            $table->timestamps();

            $table->foreign('apple_health_record_id', 'ahr_obs_record_fk')
                ->references('id')
                ->on('apple_health_records')
                ->cascadeOnDelete();
            $table->foreign('apple_health_source_set_id', 'ahr_obs_source_set_fk')
                ->references('id')
                ->on('apple_health_source_sets')
                ->cascadeOnDelete();
            $table->unique(
                ['apple_health_record_id', 'apple_health_source_set_id'],
                'apple_health_record_observations_unique'
            );
        });

        Schema::create('apple_health_workout_observations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apple_health_workout_id');
            $table->unsignedBigInteger('apple_health_source_set_id');
            $table->timestamps();

            $table->foreign('apple_health_workout_id', 'ahw_obs_workout_fk')
                ->references('id')
                ->on('apple_health_workouts')
                ->cascadeOnDelete();
            $table->foreign('apple_health_source_set_id', 'ahw_obs_source_set_fk')
                ->references('id')
                ->on('apple_health_source_sets')
                ->cascadeOnDelete();
            $table->unique(
                ['apple_health_workout_id', 'apple_health_source_set_id'],
                'apple_health_workout_observations_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_health_workout_observations');
        Schema::dropIfExists('apple_health_record_observations');
        Schema::dropIfExists('apple_health_workouts');
        Schema::dropIfExists('apple_health_records');
        Schema::dropIfExists('apple_health_source_sets');
    }
};
