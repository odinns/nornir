<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Data\Intake\RecordIntakeResultData;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

trait InteractsWithImportConsole
{
    protected function printIntakeSummary(int $intakeRecordId, string $reviewManifestPath): void
    {
        $this->line("Intake record: {$intakeRecordId}");
        $this->line("Review manifest: {$reviewManifestPath}");
    }

    /**
     * @param  array<string, int|string>  $summary
     * @param  array<string, string>  $labels
     */
    protected function printImportCompletion(int $runId, string $runStatus, array $summary, array $labels): void
    {
        $this->info('Import complete');
        $this->line("Run id: {$runId}");
        $this->line("Run status: {$runStatus}");

        foreach ($labels as $summaryKey => $label) {
            $value = $summary[$summaryKey] ?? null;

            if (! is_int($value) && ! is_string($value)) {
                continue;
            }

            $this->line("{$label}: {$value}");
        }
    }

    protected function resolveFilesystemAccessMode(string $source): string
    {
        if (File::isDirectory($source)) {
            return 'local-path';
        }

        if (File::isFile($source)) {
            return 'archive';
        }

        throw new InvalidArgumentException('Source locator is not reachable.');
    }

    protected function sourceArgument(): string
    {
        $source = $this->argument('source');

        if (! is_string($source) || $source === '') {
            throw new InvalidArgumentException('Source locator is not reachable.');
        }

        return $source;
    }

    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    protected function stringOptions(string $name): array
    {
        $value = $this->option($name);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $scopeSnapshot
     * @param  array<string, mixed>  $importerOptions
     */
    protected function recordImportIntake(
        RecordIntakeAction $recordIntakeAction,
        string $sourceType,
        string $accessMode,
        string $sourceLocator,
        array $scopeSnapshot,
        array $importerOptions = [],
    ): RecordIntakeResultData {
        return ($recordIntakeAction)(new RecordIntakeData(
            sourceType: $sourceType,
            accessMode: $accessMode,
            sourceLocator: $sourceLocator,
            scopeSnapshot: $scopeSnapshot,
            importerOptions: $importerOptions,
        ));
    }
}
