<?php

declare(strict_types=1);

namespace App\Search;

use App\Models\SearchDocument;
use Illuminate\Database\Eloquent\Collection;

final class ScoutSearchIndexer implements SearchIndexer
{
    public function flush(?string $sourceType = null): void
    {
        if ($sourceType === null) {
            SearchDocument::removeAllFromSearch();

            return;
        }

        SearchDocument::query()
            ->where('source_type', $sourceType)
            ->chunkById(500, static function (Collection $documents): void {
                /** @phpstan-ignore-next-line method.notFound */
                $documents->unsearchable();
            });
    }

    public function import(?string $sourceType = null): void
    {
        $query = SearchDocument::query();

        if ($sourceType !== null) {
            $query->where('source_type', $sourceType);
        }

        $query->chunkById(500, static function (Collection $documents): void {
            /** @phpstan-ignore-next-line method.notFound */
            $documents->searchable();
        });
    }
}
