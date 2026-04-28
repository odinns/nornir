<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use App\Models\MediaFile;

class BuildMediaSourcePageHandoffAction
{
    private const string TABLE = 'media_files';

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'media-collection-import',
            errorMessage: 'Run does not describe a successful media-collection import.',
        );

        $run = $boundary['run'];
        $scopeSnapshot = $boundary['scope_snapshot'];

        $volumeFilter = is_string($scopeSnapshot['volume'] ?? null) && $scopeSnapshot['volume'] !== ''
            ? $scopeSnapshot['volume']
            : null;

        $query = MediaFile::query();

        if ($volumeFilter !== null) {
            $query->where('volume_label', $volumeFilter);
        }

        $fileCount = (int) $query->count();

        $volumesQuery = MediaFile::query()
            ->when($volumeFilter !== null, static fn ($q) => $q->where('volume_label', $volumeFilter))
            ->distinct()
            ->pluck('volume_label');

        /** @var list<string> $volumes */
        $volumes = $volumesQuery->sort()->values()->all();

        return new WikiCompilationHandoffData(
            sourceType: 'media-collection',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: [
                'tables' => [self::TABLE],
                'volume_filter' => $volumeFilter,
                'row_counts' => [
                    'media_files' => $fileCount,
                ],
                'volumes' => $volumes,
            ],
        );
    }
}
