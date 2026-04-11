<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildTwitterSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildTwitterSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:twitter-source-pages
        {--run-id= : Explicit successful Twitter import run id to build from}';

    protected $description = 'Build the compile-facing Twitter source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildTwitterSourcePageHandoffAction $buildTwitterSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRunId();
        $handoff = ($this->buildTwitterSourcePageHandoffAction)($runId);
        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        $this->info('Building Twitter source-page handoff');
        $this->line("Using run id: {$runId}");

        if (is_string($sourceLocator) && $sourceLocator !== '') {
            $this->line("Source locator: {$sourceLocator}");
        }

        $this->line('Source set count: '.($rowCounts['source_sets'] ?? 0));
        $this->line('Tweet count: '.($rowCounts['tweets'] ?? 0));
        $this->line('Note tweet count: '.($rowCounts['note_tweets'] ?? 0));
        $this->info('Handoff ready');

        return self::SUCCESS;
    }

    private function resolveRunId(): int
    {
        $runId = $this->option('run-id');

        if (is_string($runId) && $runId !== '') {
            return (int) $runId;
        }

        return $this->resolveLatestSuccessfulRunId(
            operation: 'twitter-import',
            errorMessage: 'No successful Twitter import run is available for handoff.',
        );
    }
}
