<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildAppleHealthSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'apple_health_source_sets',
        'apple_health_records',
        'apple_health_workouts',
        'apple_health_record_observations',
        'apple_health_workout_observations',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'apple-health-import',
            errorMessage: 'Run does not describe a successful Apple Health import.',
        );
        $run = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];

        $sourceSetIds = $this->sourcePageHandoffSupport->resolveSourceSetIds('apple_health_source_sets', $sourceLocator);

        if ($sourceSetIds === []) {
            throw new InvalidArgumentException('No canonical Apple Health rows were found for the requested run.');
        }

        $recordCount = (int) DB::table('apple_health_record_observations')
            ->whereIn('apple_health_source_set_id', $sourceSetIds)
            ->distinct()
            ->count('apple_health_record_id');

        $workoutCount = (int) DB::table('apple_health_workout_observations')
            ->whereIn('apple_health_source_set_id', $sourceSetIds)
            ->distinct()
            ->count('apple_health_workout_id');

        return new WikiCompilationHandoffData(
            sourceType: 'apple-health',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: [
                'source_locator' => $this->sourcePageHandoffSupport->normalizePath($sourceLocator),
                'accepted_root_paths' => $this->sourcePageHandoffSupport->normalizePaths(
                    $scopeSnapshot['accepted_root_paths'] ?? [],
                ),
                'tables' => self::CANONICAL_TABLES,
                'source_set_ids' => $sourceSetIds,
                'handoff_scope' => [
                    'source_set_ids' => $sourceSetIds,
                ],
                'row_counts' => [
                    'source_sets' => count($sourceSetIds),
                    'records' => $recordCount,
                    'workouts' => $workoutCount,
                ],
            ],
        );
    }
}
