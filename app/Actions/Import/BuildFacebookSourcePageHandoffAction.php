<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use App\Models\FacebookCommentObservation;
use App\Models\FacebookMessage;
use App\Models\FacebookMessageObservation;
use App\Models\FacebookPostObservation;
use App\Models\FacebookProfileSnapshot;
use App\Models\FacebookReaction;
use App\Models\FacebookReactionObservation;
use App\Models\FacebookThread;
use InvalidArgumentException;

class BuildFacebookSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'facebook_archives',
        'facebook_people',
        'facebook_profile_snapshots',
        'facebook_social_edges',
        'facebook_threads',
        'facebook_thread_participants',
        'facebook_messages',
        'facebook_message_observations',
        'facebook_message_reactions',
        'facebook_attachments',
        'facebook_posts',
        'facebook_post_observations',
        'facebook_comments',
        'facebook_comment_observations',
        'facebook_reactions',
        'facebook_reaction_observations',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'facebook-import',
            errorMessage: 'Run does not describe a successful Facebook import.',
        );
        $run = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];

        $normalizedSourceLocator = $this->sourcePageHandoffSupport->normalizePath($sourceLocator);
        $normalizedAcceptedRootPaths = $this->sourcePageHandoffSupport->normalizePaths(
            $scopeSnapshot['accepted_root_paths'] ?? []
        );

        $archiveIds = $this->sourcePageHandoffSupport->resolveSourceSetIds('facebook_archives', $sourceLocator);

        if ($archiveIds === []) {
            throw new InvalidArgumentException('No canonical Facebook rows were found for the requested run.');
        }

        $messageIds = FacebookMessageObservation::query()
            ->whereIn('facebook_archive_id', $archiveIds)
            ->pluck('facebook_message_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $threadIds = $messageIds === []
            ? []
            : FacebookMessage::query()
                ->whereIn('id', $messageIds)
                ->pluck('facebook_thread_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

        $peopleFromThreads = $threadIds === []
            ? []
            : FacebookThread::query()
                ->with('participants:id')
                ->whereIn('id', $threadIds)
                ->get()
                ->flatMap(static fn (FacebookThread $thread) => $thread->participants->pluck('id'))
                ->unique()
                ->values()
                ->all();

        $peopleFromReactions = FacebookReaction::query()
            ->whereIn('facebook_archive_id', $archiveIds)
            ->whereNotNull('facebook_person_id')
            ->pluck('facebook_person_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $profilePeople = FacebookProfileSnapshot::query()
            ->whereIn('facebook_archive_id', $archiveIds)
            ->whereNotNull('facebook_person_id')
            ->pluck('facebook_person_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $peopleIds = array_values(array_unique([...$peopleFromThreads, ...$peopleFromReactions, ...$profilePeople]));
        $postIds = FacebookPostObservation::query()
            ->whereIn('facebook_archive_id', $archiveIds)
            ->pluck('facebook_post_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $commentIds = FacebookCommentObservation::query()
            ->whereIn('facebook_archive_id', $archiveIds)
            ->pluck('facebook_comment_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $reactionIds = FacebookReactionObservation::query()
            ->whereIn('facebook_archive_id', $archiveIds)
            ->pluck('facebook_reaction_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $canonicalScope = [
            'source_locator' => $normalizedSourceLocator,
            'accepted_root_paths' => $normalizedAcceptedRootPaths,
            'tables' => self::CANONICAL_TABLES,
            'source_set_ids' => $archiveIds,
            'handoff_scope' => [
                'source_set_ids' => $archiveIds,
            ],
            'row_counts' => [
                'source_sets' => count($archiveIds),
                'conversations' => count($threadIds),
                'messages' => count($messageIds),
                'people' => count($peopleIds),
                'posts' => count(array_unique($postIds)),
                'comments' => count(array_unique($commentIds)),
                'reactions' => count(array_unique($reactionIds)),
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'facebook',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
