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
            $table->fullText('body_plain');
        });
    }

    public function down(): void
    {
        Schema::table('gmail_messages', function (Blueprint $table): void {
            $table->dropFullText(['body_plain']);
        });
    }
};
