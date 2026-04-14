<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildGmailSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildGmailSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:gmail-source-pages
        {--run-id= : Explicit successful Gmail import run id to build from}';

    protected $description = 'Build the compile-facing Gmail source-page handoff from imported rows.';

    public function __construct(
        private readonly BuildGmailSourcePageHandoffAction $buildGmailSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRequestedOrLatestRunId(
            operation: 'gmail-import',
            errorMessage: 'No successful Gmail import run is available for handoff.',
        );
        $handoff = ($this->buildGmailSourcePageHandoffAction)($runId);
        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $accountEmail = $handoff->canonicalScope['account_email'] ?? null;

        $this->printHandoffSummary(
            label: 'Gmail',
            runId: $runId,
            rowCounts: $rowCounts,
            sourceLocator: is_string($accountEmail) && $accountEmail !== '' ? "account:{$accountEmail}" : null,
        );

        return self::SUCCESS;
    }
}
