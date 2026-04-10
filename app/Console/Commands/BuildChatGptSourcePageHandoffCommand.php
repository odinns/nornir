<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildChatGptSourcePageHandoffAction;
use App\Models\Run;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BuildChatGptSourcePageHandoffCommand extends Command
{
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
        $runId = $this->resolveRunId();

        $this->info('Building ChatGPT source-page handoff');
        $this->line("Using run id: {$runId}");

        $handoff = ($this->buildChatGptSourcePageHandoffAction)($runId);

        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $sourceLocator = $handoff->canonicalScope['source_locator'] ?? '';

        if (is_string($sourceLocator) && $sourceLocator !== '') {
            $this->line("Source locator: {$sourceLocator}");
        }

        $this->line('Archive count: '.($rowCounts['archives'] ?? 0));
        $this->line('Conversation count: '.($rowCounts['conversations'] ?? 0));
        $this->line('Message count: '.($rowCounts['messages'] ?? 0));
        $this->info('Handoff ready');

        return self::SUCCESS;
    }

    private function resolveRunId(): int
    {
        $runId = $this->option('run-id');

        if (is_string($runId) && $runId !== '') {
            return (int) $runId;
        }

        $resolvedRunId = Run::query()
            ->where('subsystem', 'import')
            ->where('operation', 'chatgpt-import')
            ->where('status', Run::STATUS_SUCCEEDED)
            ->latest('id')
            ->value('id');

        if (! is_int($resolvedRunId)) {
            throw new InvalidArgumentException('No successful ChatGPT import run is available for handoff.');
        }

        return $resolvedRunId;
    }
}
