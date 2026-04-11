<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
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

        $messageCount = (int) DB::table('facebook_message_observations')
            ->whereIn('facebook_archive_id', $archiveIds)
            ->distinct()
            ->count('facebook_message_id');

        $conversationCount = (int) DB::table('facebook_message_observations')
            ->join('facebook_messages', 'facebook_messages.id', '=', 'facebook_message_observations.facebook_message_id')
            ->whereIn('facebook_message_observations.facebook_archive_id', $archiveIds)
            ->distinct()
            ->count('facebook_messages.facebook_thread_id');

        $peopleFromThreads = DB::table('facebook_message_observations')
            ->join('facebook_messages', 'facebook_messages.id', '=', 'facebook_message_observations.facebook_message_id')
            ->join('facebook_thread_participants', 'facebook_thread_participants.facebook_thread_id', '=', 'facebook_messages.facebook_thread_id')
            ->whereIn('facebook_message_observations.facebook_archive_id', $archiveIds)
            ->distinct()
            ->pluck('facebook_thread_participants.facebook_person_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $peopleFromReactions = DB::table('facebook_reactions')
            ->whereIn('facebook_archive_id', $archiveIds)
            ->whereNotNull('facebook_person_id')
            ->pluck('facebook_person_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $profilePeople = DB::table('facebook_profile_snapshots')
            ->whereIn('facebook_archive_id', $archiveIds)
            ->whereNotNull('facebook_person_id')
            ->pluck('facebook_person_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $peopleIds = array_values(array_unique([...$peopleFromThreads, ...$peopleFromReactions, ...$profilePeople]));
        $postIds = DB::table('facebook_post_observations')
            ->whereIn('facebook_archive_id', $archiveIds)
            ->pluck('facebook_post_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $commentIds = DB::table('facebook_comment_observations')
            ->whereIn('facebook_archive_id', $archiveIds)
            ->pluck('facebook_comment_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $reactionIds = DB::table('facebook_reaction_observations')
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
                'conversations' => $conversationCount,
                'messages' => $messageCount,
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
