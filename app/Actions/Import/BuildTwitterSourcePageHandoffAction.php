<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use App\Models\TwitterAccount;
use App\Models\TwitterArchive;
use App\Models\TwitterMediaRefObservation;
use App\Models\TwitterNoteTweetObservation;
use App\Models\TwitterProfileSnapshot;
use App\Models\TwitterTweetObservation;
use InvalidArgumentException;

class BuildTwitterSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'twitter_archives',
        'twitter_accounts',
        'twitter_profile_snapshots',
        'twitter_screen_name_changes',
        'twitter_tweets',
        'twitter_note_tweets',
        'twitter_media_refs',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'twitter-import',
            errorMessage: 'Run does not describe a successful Twitter import.',
        );

        $run = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];

        $archiveIds = $this->sourcePageHandoffSupport->resolveSourceSetIds('twitter_archives', $sourceLocator);

        if ($archiveIds === []) {
            throw new InvalidArgumentException('No canonical Twitter rows were found for the requested run.');
        }

        $sourceSetIds = $archiveIds;

        $accountIds = TwitterArchive::query()
            ->whereIn('id', $sourceSetIds)
            ->whereNotNull('account_id')
            ->pluck('account_id')
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();

        $canonicalScope = [
            'source_locator' => $this->sourcePageHandoffSupport->normalizePath($sourceLocator),
            'accepted_root_paths' => $this->sourcePageHandoffSupport->normalizePaths(
                $scopeSnapshot['accepted_root_paths'] ?? []
            ),
            'tables' => self::CANONICAL_TABLES,
            'source_set_ids' => $sourceSetIds,
            'handoff_scope' => [
                'source_set_ids' => $sourceSetIds,
                'account_ids' => $accountIds,
            ],
            'row_counts' => [
                'source_sets' => count($sourceSetIds),
                'accounts' => TwitterAccount::query()->whereIn('twitter_archive_id', $sourceSetIds)->count(),
                'profile_snapshots' => TwitterProfileSnapshot::query()->whereIn('twitter_archive_id', $sourceSetIds)->count(),
                'tweets' => TwitterTweetObservation::query()
                    ->whereIn('twitter_archive_id', $sourceSetIds)
                    ->distinct()
                    ->count('twitter_tweet_id'),
                'note_tweets' => TwitterNoteTweetObservation::query()
                    ->whereIn('twitter_archive_id', $sourceSetIds)
                    ->distinct()
                    ->count('twitter_note_tweet_id'),
                'media_refs' => TwitterMediaRefObservation::query()
                    ->whereIn('twitter_archive_id', $sourceSetIds)
                    ->distinct()
                    ->count('twitter_media_ref_id'),
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'twitter',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
