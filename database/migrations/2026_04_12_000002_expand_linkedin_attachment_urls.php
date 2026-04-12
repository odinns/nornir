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
        if (Schema::hasColumn('linkedin_message_attachments', 'attachment_url')) {
            Schema::table('linkedin_message_attachments', function (Blueprint $table): void {
                $table->dropColumn('attachment_url');
            });
        }

        Schema::table('linkedin_message_attachments', function (Blueprint $table): void {
            if (! Schema::hasColumn('linkedin_message_attachments', 'attachment_urls_json')) {
                $table->json('attachment_urls_json')->nullable()->after('attachment_key');
            }
        });

        DB::table('linkedin_message_attachments')
            ->whereNull('attachment_urls_json')
            ->update([
                'attachment_urls_json' => json_encode([], JSON_THROW_ON_ERROR),
            ]);

        DB::statement('ALTER TABLE linkedin_message_attachments MODIFY attachment_urls_json JSON NOT NULL');
        DB::statement('ALTER TABLE linkedin_message_attachments ADD UNIQUE li_message_attachments_message_unique (linkedin_message_id)');
    }

    public function down(): void
    {
        Schema::table('linkedin_message_attachments', function (Blueprint $table): void {
            if (Schema::hasColumn('linkedin_message_attachments', 'attachment_urls_json')) {
                $table->dropUnique('li_message_attachments_message_unique');
                $table->dropColumn('attachment_urls_json');
            }
        });

        Schema::table('linkedin_message_attachments', function (Blueprint $table): void {
            if (! Schema::hasColumn('linkedin_message_attachments', 'attachment_url')) {
                $table->string('attachment_url')->nullable()->after('attachment_key');
            }
        });

        DB::statement('ALTER TABLE linkedin_message_attachments MODIFY attachment_url VARCHAR(255) NOT NULL');
    }
};
