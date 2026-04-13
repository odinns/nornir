<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_file_id')->unique();
            $table->string('volume_label');
            $table->string('volume_mount_path', 500)->nullable();
            $table->text('directory_full_path');
            $table->string('event_label', 500)->nullable();
            $table->date('event_date')->nullable();
            $table->string('basename');
            $table->string('extension', 50)->nullable();
            $table->string('normalized_file_type', 50);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('fs_created_at')->nullable();
            $table->timestamp('fs_modified_at')->nullable();
            $table->string('duplicate_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
