<?php

declare(strict_types=1);

namespace App\Actions\Import\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SourceObservationStore
{
    /**
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    public function upsertAndReturnId(string $table, array $unique, array $values): int
    {
        $timestamps = [
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $inserted = DB::table($table)->insertOrIgnore([
            ...$unique,
            ...$values,
            ...$timestamps,
        ]);

        if ($inserted === 0) {
            $this->queryForUnique($table, $unique)->update([
                ...$values,
                'updated_at' => now(),
            ]);
        }

        return (int) $this->queryForUnique($table, $unique)->value('id');
    }

    /**
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    public function record(string $table, array $unique, array $values = []): void
    {
        $inserted = DB::table($table)->insertOrIgnore([
            ...$unique,
            ...$values,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            $this->queryForUnique($table, $unique)->update([
                ...$values,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $unique
     */
    private function queryForUnique(string $table, array $unique): Builder
    {
        $query = DB::table($table);

        foreach ($unique as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }
}
