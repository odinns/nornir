<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildLinkedInSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildLinkedInSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:linkedin-source-pages
        {--run-id= : Explicit successful LinkedIn import run id to build from}';

    protected $description = 'Build the compile-facing LinkedIn source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildLinkedInSourcePageHandoffAction $buildLinkedInSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRequestedOrLatestRunId(
            operation: 'linkedin-import',
            errorMessage: 'No successful LinkedIn import run is available for handoff.',
        );
        $handoff = ($this->buildLinkedInSourcePageHandoffAction)($runId);
        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        $this->printHandoffSummary('LinkedIn', $runId, $rowCounts, is_string($sourceLocator) ? $sourceLocator : null);

        return self::SUCCESS;
    }
}
