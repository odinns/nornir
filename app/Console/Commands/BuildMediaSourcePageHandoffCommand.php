<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildMediaSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildMediaSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:media-source-pages
        {--run-id= : Explicit successful media-collection import run id to build from}';

    protected $description = 'Build the compile-facing media-collection source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildMediaSourcePageHandoffAction $buildMediaSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRequestedOrLatestRunId(
            operation: 'media-collection-import',
            errorMessage: 'No successful media-collection import run is available for handoff.',
        );
        $handoff = ($this->buildMediaSourcePageHandoffAction)($runId);
        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $volumeFilter = $handoff->canonicalScope['volume_filter'] ?? null;

        $this->info('Building media-collection source-page handoff');
        $this->line("Using run id: {$runId}");

        if (is_string($volumeFilter) && $volumeFilter !== '') {
            $this->line("Volume filter: {$volumeFilter}");
        }

        $this->line('Media file count: '.($rowCounts['media_files'] ?? 0));
        $this->info('Handoff ready');

        return self::SUCCESS;
    }
}
