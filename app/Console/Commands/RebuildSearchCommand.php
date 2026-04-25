<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SearchDocument;
use App\Search\SearchDocumentBuilderRegistry;
use App\Search\SearchIndexer;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class RebuildSearchCommand extends Command
{
    protected $signature = 'search:rebuild {--source= : Limit rebuild to one source type} {--dry-run : Count documents without writing projection rows or updating Scout}';

    protected $description = 'Rebuild disposable search_documents projection rows and import them into Scout.';

    public function handle(SearchDocumentBuilderRegistry $registry, SearchIndexer $indexer): int
    {
        $source = $this->option('source');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_string($source) && $source !== null) {
            $this->error('The --source option must be a string.');

            return self::FAILURE;
        }

        try {
            $builders = $registry->builders($source === '' ? null : $source);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            $this->line('Available sources: '.implode(', ', $registry->sourceTypes()));

            return self::FAILURE;
        }

        $counts = [];
        $normalizedSource = is_string($source) && $source !== '' ? $source : null;

        if (! $dryRun) {
            $indexer->flush($normalizedSource);

            SearchDocument::withoutSyncingToSearch(function () use ($normalizedSource): void {
                $query = SearchDocument::query();

                if ($normalizedSource !== null) {
                    $query->where('source_type', $normalizedSource);
                }

                $query->delete();
            });
        }

        foreach ($builders as $builder) {
            $indexed = 0;
            $skipped = 0;

            foreach ($builder->build() as $document) {
                if (! $document->hasSearchableText()) {
                    $skipped++;

                    continue;
                }

                $indexed++;

                if ($dryRun) {
                    continue;
                }

                SearchDocument::withoutSyncingToSearch(
                    static fn (): SearchDocument => SearchDocument::query()->create($document->toAttributes()),
                );
            }

            $counts[$builder->sourceType()] = ['indexed' => $indexed, 'skipped' => $skipped];
        }

        if (! $dryRun) {
            $indexer->import($normalizedSource);
        }

        $this->table(
            ['Source', 'Indexed', 'Skipped'],
            collect($counts)
                ->map(static fn (array $count, string $sourceType): array => [
                    $sourceType,
                    $count['indexed'],
                    $count['skipped'],
                ])
                ->values()
                ->all(),
        );

        $this->info($dryRun ? 'Search rebuild dry run complete.' : 'Search rebuild complete.');

        return self::SUCCESS;
    }
}
