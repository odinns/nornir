<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildAppleHealthSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildAppleHealthSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:apple-health-source-pages
        {--run-id= : Explicit successful Apple Health import run id to build from}';

    protected $description = 'Build the compile-facing Apple Health source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildAppleHealthSourcePageHandoffAction $buildAppleHealthSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRequestedOrLatestRunId(
            operation: 'apple-health-import',
            errorMessage: 'No successful Apple Health import run is available for handoff.',
        );

        $handoff = ($this->buildAppleHealthSourcePageHandoffAction)($runId);

        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        $this->info('Building Apple Health source-page handoff');
        $this->line("Using run id: {$runId}");

        if (is_string($sourceLocator) && $sourceLocator !== '') {
            $this->line("Source locator: {$sourceLocator}");
        }

        $this->line('Source set count: '.($rowCounts['source_sets'] ?? 0));
        $this->line('Record count: '.($rowCounts['records'] ?? 0));
        $this->line('Workout count: '.($rowCounts['workouts'] ?? 0));
        $this->info('Handoff ready');

        return self::SUCCESS;
    }
}
