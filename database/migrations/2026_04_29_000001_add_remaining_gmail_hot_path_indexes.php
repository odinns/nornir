<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX gmail_messages_received_at_to_header_idx ON gmail_messages (message_received_at, to_header(191))');
        DB::statement('CREATE INDEX gmail_messages_received_at_subject_idx ON gmail_messages (message_received_at, subject(191))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX gmail_messages_received_at_subject_idx ON gmail_messages');
        DB::statement('DROP INDEX gmail_messages_received_at_to_header_idx ON gmail_messages');
    }
};
