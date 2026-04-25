<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildInstagramSourcePageHandoffAction
{
    private const string TABLE_ACCOUNTS = 'instagram_accounts';

    private const string TABLE_POSTS = 'instagram_posts';

    private const string TABLE_MEDIA_REFS = 'instagram_media_refs';

    private const string TABLE_PROFILE_SNAPSHOTS = 'instagram_profile_snapshots';

    private const array CANONICAL_TABLES = [
        self::TABLE_ACCOUNTS,
        self::TABLE_PROFILE_SNAPSHOTS,
        self::TABLE_POSTS,
        self::TABLE_MEDIA_REFS,
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'instagram-import',
            errorMessage: 'Run does not describe a successful Instagram import.',
        );

        $run = $boundary['run'];
        $snapshotIds = $this->sourcePageHandoffSupport->resolveProvenanceOutputRefs($run->id, self::TABLE_PROFILE_SNAPSHOTS);

        $accountIds = $snapshotIds === []
            ? []
            : DB::table(self::TABLE_PROFILE_SNAPSHOTS)
                ->whereIn('id', array_map(intval(...), $snapshotIds))
                ->distinct()
                ->pluck('instagram_account_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

        if ($accountIds === []) {
            $postIds = $this->sourcePageHandoffSupport->resolveProvenanceOutputRefs($run->id, self::TABLE_POSTS);

            $accountIds = $postIds === []
                ? []
                : DB::table(self::TABLE_POSTS)
                    ->whereIn('id', array_map(intval(...), $postIds))
                    ->distinct()
                    ->pluck('instagram_account_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
        }

        $accountIds = array_values(array_unique($accountIds));

        if (count($accountIds) > 1) {
            throw new InvalidArgumentException('Instagram handoff run resolved to multiple canonical accounts.');
        }

        $account = $accountIds === []
            ? null
            : DB::table(self::TABLE_ACCOUNTS)->where('id', $accountIds[0])->first();

        if ($account === null) {
            throw new InvalidArgumentException('No canonical Instagram rows were found for the requested run.');
        }

        $accountId = (int) $account->id;
        $postCount = (int) DB::table(self::TABLE_POSTS)->where('instagram_account_id', $accountId)->count();
        $mediaRefCount = (int) DB::table(self::TABLE_MEDIA_REFS)->where('instagram_account_id', $accountId)->count();

        $canonicalScope = [
            'username' => (string) $account->username,
            'tables' => self::CANONICAL_TABLES,
            'row_counts' => [
                'posts' => $postCount,
                'media_refs' => $mediaRefCount,
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'instagram',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
