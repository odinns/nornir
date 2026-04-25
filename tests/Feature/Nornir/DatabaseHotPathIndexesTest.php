<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('adds indexes for gmail and provenance hot paths', function (): void {
    expect(indexColumns('gmail_messages', 'gmail_messages_message_received_at_index'))
        ->toBe(['message_received_at'])
        ->and(indexColumns('gmail_messages', 'gmail_messages_message_received_at_from_header_index'))
        ->toBe(['message_received_at', 'from_header'])
        ->and(indexColumns('gmail_messages', 'gmail_messages_body_plain_fulltext'))
        ->toBe(['body_plain'])
        ->and(indexColumns('provenance_links', 'provenance_links_run_id_output_target_id_index'))
        ->toBe(['run_id', 'output_target', 'id']);
});

/**
 * @return list<string>
 */
function indexColumns(string $table, string $indexName): array
{
    return collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]))
        ->sortBy('Seq_in_index')
        ->pluck('Column_name')
        ->values()
        ->all();
}
