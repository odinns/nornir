<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildFidonetSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildFidonetSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:fidonet-source-pages
        {--run-id= : Explicit successful FidoNet import run id to build from}';

    protected $description = 'Build the compile-facing FidoNet source-page handoff from imported rows.';

    public function __construct(
        private readonly BuildFidonetSourcePageHandoffAction $buildFidonetSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRequestedOrLatestRunId(
            operation: 'fidonet-import',
            errorMessage: 'No successful FidoNet import run is available for handoff.',
        );
        $handoff = ($this->buildFidonetSourcePageHandoffAction)($runId);
        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        $this->printHandoffSummary('FidoNet', $runId, $rowCounts, is_string($sourceLocator) ? $sourceLocator : null);

        return self::SUCCESS;
    }
}
