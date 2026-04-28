<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use App\Models\FidonetAreaObservation;
use App\Models\FidonetMessageObservation;
use App\Models\FidonetMessageParticipant;
use App\Models\FidonetSource;
use App\Models\FidonetThreadObservation;
use InvalidArgumentException;

class BuildFidonetSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'fidonet_sources',
        'fidonet_areas',
        'fidonet_messages',
        'fidonet_participants',
        'fidonet_message_participants',
        'fidonet_threads',
        'fidonet_thread_messages',
        'fidonet_message_cleanup',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'fidonet-import',
            errorMessage: 'Run does not describe a successful FidoNet import.',
        );

        $run = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];
        $scopeHash = sha1(json_encode($scopeSnapshot, JSON_THROW_ON_ERROR));
        $sourceIds = FidonetSource::query()
            ->where('source_locator', $sourceLocator)
            ->where('scope_hash', $scopeHash)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($sourceIds === []) {
            throw new InvalidArgumentException('No imported FidoNet rows were found for the requested run.');
        }

        $threadIds = FidonetThreadObservation::query()
            ->whereIn('fidonet_source_id', $sourceIds)
            ->pluck('fidonet_thread_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $messageIds = FidonetMessageObservation::query()
            ->whereIn('fidonet_source_id', $sourceIds)
            ->pluck('canonical_message_id')
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        $participantCount = $messageIds === []
            ? 0
            : FidonetMessageParticipant::query()
                ->whereIn('canonical_message_id', $messageIds)
                ->distinct()
                ->count('fidonet_participant_id');

        $canonicalScope = [
            'source_locator' => $sourceLocator,
            'tables' => self::CANONICAL_TABLES,
            'source_set_ids' => $sourceIds,
            'handoff_scope' => [
                'source_set_ids' => $sourceIds,
                'selection_mode' => $scopeSnapshot['selection_mode'] ?? 'odinn-thread-scope',
            ],
            'row_counts' => [
                'source_sets' => count($sourceIds),
                'areas' => FidonetAreaObservation::query()->whereIn('fidonet_source_id', $sourceIds)->count(),
                'threads' => count(array_unique($threadIds)),
                'messages' => count($messageIds),
                'participants' => $participantCount,
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'fidonet',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
