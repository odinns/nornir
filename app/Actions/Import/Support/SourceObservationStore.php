<?php

declare(strict_types=1);

namespace App\Actions\Import\Support;

use Illuminate\Support\Facades\DB;

class SourceObservationStore
{
    /**
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    public function upsertAndReturnId(string $table, array $unique, array $values): int
    {
        DB::table($table)->updateOrInsert($unique, $this->withTimestamps($values));

        $query = DB::table($table);

        foreach ($unique as $column => $value) {
            $query->where($column, $value);
        }

        return (int) $query->value('id');
    }

    /**
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    public function record(string $table, array $unique, array $values = []): void
    {
        DB::table($table)->updateOrInsert($unique, $this->withTimestamps($values));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withTimestamps(array $values): array
    {
        return [
            ...$values,
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }
}
