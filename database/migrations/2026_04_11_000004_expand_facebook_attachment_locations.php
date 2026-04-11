<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE facebook_attachments MODIFY relative_path TEXT NULL');
        DB::statement('ALTER TABLE facebook_attachments MODIFY source_uri TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE facebook_attachments MODIFY relative_path VARCHAR(255) NULL');
        DB::statement('ALTER TABLE facebook_attachments MODIFY source_uri VARCHAR(255) NULL');
    }
};
