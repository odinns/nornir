<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps mysql hot path indexes for gmail analytics and provenance lookups', function (): void {
    $gmailIndexes = mysqlHotPathIndexColumns('gmail_messages');

    expect($gmailIndexes['gmail_messages_received_at_to_header_idx'] ?? null)->toBe([
        ['column' => 'message_received_at', 'sub_part' => null],
        ['column' => 'to_header', 'sub_part' => 191],
    ]);

    expect($gmailIndexes['gmail_messages_received_at_subject_idx'] ?? null)->toBe([
        ['column' => 'message_received_at', 'sub_part' => null],
        ['column' => 'subject', 'sub_part' => 191],
    ]);

    $provenanceIndexes = mysqlHotPathIndexColumns('provenance_links');

    expect($provenanceIndexes['provenance_links_run_id_output_target_id_index'] ?? null)->toBe([
        ['column' => 'run_id', 'sub_part' => null],
        ['column' => 'output_target', 'sub_part' => null],
        ['column' => 'id', 'sub_part' => null],
    ]);
});

/**
 * @return array<string, list<array{column: string, sub_part: int|null}>>
 */
function mysqlHotPathIndexColumns(string $table): array
{
    $quotedTable = str_replace('`', '``', $table);

    /** @var list<object> $rows */
    $rows = DB::select("SHOW INDEX FROM `{$quotedTable}`");

    /** @var array<string, array<int, array{column: string, sub_part: int|null}>> $indexes */
    $indexes = [];

    foreach ($rows as $row) {
        /** @var array{Key_name: string, Seq_in_index: int|string, Column_name: string, Sub_part?: int|string|null} $index */
        $index = (array) $row;
        $keyName = $index['Key_name'];
        $sequence = (int) $index['Seq_in_index'];
        $subPart = $index['Sub_part'] ?? null;

        $indexes[$keyName][$sequence] = [
            'column' => $index['Column_name'],
            'sub_part' => $subPart === null ? null : (int) $subPart,
        ];
    }

    foreach ($indexes as $keyName => $columns) {
        ksort($columns);
        $indexes[$keyName] = array_values($columns);
    }

    return $indexes;
}
