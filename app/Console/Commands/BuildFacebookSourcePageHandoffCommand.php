<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildFacebookSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildFacebookSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:facebook-source-pages
        {--run-id= : Explicit successful Facebook import run id to build from}';

    protected $description = 'Build the compile-facing Facebook source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildFacebookSourcePageHandoffAction $buildFacebookSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRequestedOrLatestRunId(
            operation: 'facebook-import',
            errorMessage: 'No successful Facebook import run is available for handoff.',
        );

        $handoff = ($this->buildFacebookSourcePageHandoffAction)($runId);

        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        $this->printHandoffSummary('Facebook', $runId, $rowCounts, is_string($sourceLocator) ? $sourceLocator : null);

        return self::SUCCESS;
    }
}
