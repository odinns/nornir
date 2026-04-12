<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatgpt_conversations', function (Blueprint $table): void {
            $table->timestamp('conversation_created_at')->nullable()->after('source_create_time')->index();
            $table->timestamp('conversation_updated_at')->nullable()->after('source_update_time')->index();
        });

        Schema::table('chatgpt_messages', function (Blueprint $table): void {
            $table->timestamp('message_created_at')->nullable()->after('source_create_time')->index();
            $table->timestamp('message_updated_at')->nullable()->after('source_update_time')->index();
        });

        DB::table('chatgpt_conversations')
            ->select(['id', 'source_create_time', 'source_update_time'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('chatgpt_conversations')
                        ->where('id', $row->id)
                        ->update([
                            'conversation_created_at' => self::normalizeTimestamp($row->source_create_time),
                            'conversation_updated_at' => self::normalizeTimestamp($row->source_update_time),
                        ]);
                }
            });

        DB::table('chatgpt_messages')
            ->select(['id', 'source_create_time', 'source_update_time'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('chatgpt_messages')
                        ->where('id', $row->id)
                        ->update([
                            'message_created_at' => self::normalizeTimestamp($row->source_create_time),
                            'message_updated_at' => self::normalizeTimestamp($row->source_update_time),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('chatgpt_messages', function (Blueprint $table): void {
            $table->dropColumn(['message_created_at', 'message_updated_at']);
        });

        Schema::table('chatgpt_conversations', function (Blueprint $table): void {
            $table->dropColumn(['conversation_created_at', 'conversation_updated_at']);
        });
    }

    private static function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((float) $value)->toDateTimeString();
    }
};
