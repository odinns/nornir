<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildChatGptSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildChatGptSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:chatgpt-source-pages
        {--run-id= : Explicit successful ChatGPT import run id to build from}';

    protected $description = 'Build the compile-facing ChatGPT source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildChatGptSourcePageHandoffAction $buildChatGptSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRequestedOrLatestRunId(
            operation: 'chatgpt-import',
            errorMessage: 'No successful ChatGPT import run is available for handoff.',
        );

        $handoff = ($this->buildChatGptSourcePageHandoffAction)($runId);

        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        $this->printHandoffSummary('ChatGPT', $runId, $rowCounts, is_string($sourceLocator) ? $sourceLocator : null);

        return self::SUCCESS;
    }
}
