<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('account_key', 40)->unique();
            $table->string('account_email')->unique();
            $table->string('display_name')->nullable();
            $table->string('access_mode', 32)->default('api');
            $table->timestamps();
        });

        Schema::create('gmail_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gmail_account_id')->constrained('gmail_accounts')->cascadeOnDelete();
            $table->string('thread_id');
            $table->text('snippet')->nullable();
            $table->string('history_id', 64)->nullable();
            $table->json('raw_thread')->nullable();
            $table->timestamps();
            $table->unique(['gmail_account_id', 'thread_id']);
        });

        Schema::create('gmail_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gmail_thread_id')->constrained('gmail_threads')->cascadeOnDelete();
            $table->string('message_id')->unique();
            $table->string('from_header', 500)->nullable();
            $table->text('to_header')->nullable();
            $table->text('cc_header')->nullable();
            $table->string('subject', 1000)->nullable();
            $table->text('snippet')->nullable();
            $table->longText('body_plain')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('raw_headers')->nullable();
            $table->unsignedBigInteger('internal_date')->nullable();
            $table->dateTime('message_received_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('gmail_message_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gmail_message_id')->constrained('gmail_messages')->cascadeOnDelete();
            $table->string('label_id');
            $table->string('label_name')->nullable();
            $table->timestamps();
            $table->unique(['gmail_message_id', 'label_id']);
        });

        Schema::create('gmail_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gmail_message_id')->constrained('gmail_messages')->cascadeOnDelete();
            $table->string('attachment_id', 500);
            $table->string('filename', 500)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
            $table->unique(['gmail_message_id', 'attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_attachments');
        Schema::dropIfExists('gmail_message_labels');
        Schema::dropIfExists('gmail_messages');
        Schema::dropIfExists('gmail_threads');
        Schema::dropIfExists('gmail_accounts');
    }
};
