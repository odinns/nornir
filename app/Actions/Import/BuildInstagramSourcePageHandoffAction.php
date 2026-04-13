<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        $sourceLocator = $boundary['source_locator'];

        $personalInfoPath = $sourceLocator.'/personal_information/personal_information/personal_information.json';
        $contents = File::get($personalInfoPath);
        $personalInfo = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $username = (string) ($personalInfo['profile_user'][0]['string_map_data']['Username']['value'] ?? '');
        $accountKey = sha1($username);

        $account = DB::table(self::TABLE_ACCOUNTS)->where('account_key', $accountKey)->first();

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
