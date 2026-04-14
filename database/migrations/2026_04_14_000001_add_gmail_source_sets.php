<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_source_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key')->unique();
            $table->string('source_locator');
            $table->string('access_mode', 32)->default('api');
            $table->string('account_email');
            $table->text('query');
            $table->timestamps();
        });

        Schema::create('gmail_message_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gmail_message_id')->constrained('gmail_messages')->cascadeOnDelete();
            $table->foreignId('gmail_source_set_id')->constrained('gmail_source_sets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['gmail_message_id', 'gmail_source_set_id'], 'gmail_message_observations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_message_observations');
        Schema::dropIfExists('gmail_source_sets');
    }
};
