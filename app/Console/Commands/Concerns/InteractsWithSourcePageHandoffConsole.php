<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\Run;
use InvalidArgumentException;

trait InteractsWithSourcePageHandoffConsole
{
    /**
     * @param  array<string, int>  $rowCounts
     */
    protected function printHandoffSummary(string $label, int $runId, array $rowCounts, ?string $sourceLocator): void
    {
        $this->info("Building {$label} source-page handoff");
        $this->line("Using run id: {$runId}");

        if ($sourceLocator !== null && $sourceLocator !== '') {
            $this->line("Source locator: {$sourceLocator}");
        }

        $this->line('Source set count: '.($rowCounts['source_sets'] ?? 0));

        if (array_key_exists('conversations', $rowCounts)) {
            $this->line('Conversation count: '.$rowCounts['conversations']);
        } elseif (array_key_exists('threads', $rowCounts)) {
            $this->line('Thread count: '.$rowCounts['threads']);
        }

        $this->line('Message count: '.($rowCounts['messages'] ?? 0));
        $this->info('Handoff ready');
    }

    protected function resolveLatestSuccessfulRunId(string $operation, string $errorMessage): int
    {
        $resolvedRunId = Run::query()
            ->where('subsystem', 'import')
            ->where('operation', $operation)
            ->where('status', Run::STATUS_SUCCEEDED)
            ->latest('id')
            ->value('id');

        if (! is_int($resolvedRunId)) {
            throw new InvalidArgumentException($errorMessage);
        }

        return $resolvedRunId;
    }
}
