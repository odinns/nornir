<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\BuildInstagramSourcePageHandoffAction;
use App\Console\Commands\Concerns\InteractsWithSourcePageHandoffConsole;
use Illuminate\Console\Command;

class BuildInstagramSourcePageHandoffCommand extends Command
{
    use InteractsWithSourcePageHandoffConsole;

    protected $signature = 'handoff:instagram-source-pages
        {--run-id= : Explicit successful Instagram import run id to build from}';

    protected $description = 'Build the compile-facing Instagram source-page handoff from canonical rows.';

    public function __construct(
        private readonly BuildInstagramSourcePageHandoffAction $buildInstagramSourcePageHandoffAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->resolveRunId();
        $handoff = ($this->buildInstagramSourcePageHandoffAction)($runId);
        $rowCounts = $handoff->canonicalScope['row_counts'] ?? [];
        $username = $handoff->canonicalScope['username'] ?? null;

        $this->info('Building Instagram source-page handoff');
        $this->line("Using run id: {$runId}");

        if (is_string($username) && $username !== '') {
            $this->line("Account: {$username}");
        }

        $this->line('Post count: '.($rowCounts['posts'] ?? 0));
        $this->line('Media ref count: '.($rowCounts['media_refs'] ?? 0));
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
            operation: 'instagram-import',
            errorMessage: 'No successful Instagram import run is available for handoff.',
        );
    }
}
