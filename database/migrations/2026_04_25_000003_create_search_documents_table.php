<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 64)->index();
            $table->string('source_table', 128)->index();
            $table->string('source_id', 255);
            $table->text('title')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('participants')->nullable();
            $table->text('url_or_locator')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_table', 'source_id'], 'search_documents_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};
