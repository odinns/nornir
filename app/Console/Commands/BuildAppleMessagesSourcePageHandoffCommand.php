<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildAppleMessagesSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildAppleMessagesSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:apple-messages-source-pages
        {--run-id= : Explicit successful Apple Messages import run id to build from}';

    protected $description = 'Build the compile-facing Apple Messages source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildAppleMessagesSourcePageHandoffAction $buildAppleMessagesSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRequestedOrLatestRunId(
            operation: 'apple-messages-import',
            errorMessage: 'No successful Apple Messages import run is available for handoff.',
        );

        $handoff = ($this->buildAppleMessagesSourcePageHandoffAction)($runId);

        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        $this->printHandoffSummary('Apple Messages', $runId, $rowCounts, is_string($sourceLocator) ? $sourceLocator : null);

        return self::SUCCESS;
    }
}
