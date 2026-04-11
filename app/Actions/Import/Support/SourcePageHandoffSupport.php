<?php

declare(strict_types=1);

namespace App\Actions\Import\Support;

use App\Models\Run;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SourcePageHandoffSupport
{
    /**
     * @return array{run:Run, source_locator:string, scope_snapshot:array<string, mixed>}
     */
    public function resolveRunBoundary(int $runId, string $operation, string $errorMessage): array
    {
        $run = Run::query()->find($runId);

        if ($run === null
            || $run->subsystem !== 'import'
            || $run->operation !== $operation
            || $run->status !== Run::STATUS_SUCCEEDED) {
            throw new InvalidArgumentException($errorMessage);
        }

        $inputScope = $run->input_scope;
        $sourceLocator = $inputScope['source_locator'] ?? null;
        $scopeSnapshot = $inputScope['scope_snapshot'] ?? [];

        if (! is_string($sourceLocator) || ! is_array($scopeSnapshot)) {
            throw new InvalidArgumentException('Run input scope is missing the source boundary.');
        }

        return [
            'run' => $run,
            'source_locator' => $sourceLocator,
            'scope_snapshot' => $scopeSnapshot,
        ];
    }

    /**
     * @return list<int>
     */
    public function resolveSourceSetIds(string $sourceSetTable, string $sourceLocator): array
    {
        $normalizedSourceLocator = $this->normalizePath($sourceLocator);

        return array_values(DB::table($sourceSetTable)
            ->whereIn('source_locator', array_values(array_unique([$sourceLocator, $normalizedSourceLocator])))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * @return list<string>
     */
    public function normalizePaths(mixed $paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        $normalizedPaths = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $normalizedPaths[] = $this->normalizePath($path);
        }

        return $normalizedPaths;
    }

    public function normalizePath(string $path): string
    {
        $normalizedPath = realpath($path);

        if ($normalizedPath !== false) {
            return $normalizedPath;
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
