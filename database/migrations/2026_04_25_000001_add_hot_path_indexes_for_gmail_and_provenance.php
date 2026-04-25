<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gmail_messages', function (Blueprint $table): void {
            $table->index('message_received_at');
            $table->index(['message_received_at', 'from_header']);
        });

        Schema::table('provenance_links', function (Blueprint $table): void {
            $table->index(['run_id', 'output_target', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('provenance_links', function (Blueprint $table): void {
            $table->dropIndex(['run_id', 'output_target', 'id']);
        });

        Schema::table('gmail_messages', function (Blueprint $table): void {
            $table->dropIndex(['message_received_at', 'from_header']);
            $table->dropIndex(['message_received_at']);
        });
    }
};
