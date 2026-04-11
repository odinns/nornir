<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildSmsSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildSmsSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:sms-source-pages
        {--run-id= : Explicit successful SMS import run id to build from}';

    protected $description = 'Build the compile-facing SMS source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildSmsSourcePageHandoffAction $buildSmsSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRunId();

        $handoff = ($this->buildSmsSourcePageHandoffAction)($runId);

        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        $this->printHandoffSummary('SMS', $runId, $rowCounts, is_string($sourceLocator) ? $sourceLocator : null);

        return self::SUCCESS;
    }

    private function resolveRunId(): int
    {
        $runId = $this->option('run-id');

        if (is_string($runId) && $runId !== '') {
            return (int) $runId;
        }

        return $this->resolveLatestSuccessfulRunId(
            operation: 'sms-import',
            errorMessage: 'No successful SMS import run is available for handoff.',
        );
    }
}
