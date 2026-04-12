<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildLinkedInSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'linkedin_archives',
        'linkedin_people',
        'linkedin_profile_snapshots',
        'linkedin_positions',
        'linkedin_education_records',
        'linkedin_projects',
        'linkedin_skills',
        'linkedin_languages',
        'linkedin_connections',
        'linkedin_invitations',
        'linkedin_recommendations',
        'linkedin_endorsements',
        'linkedin_conversations',
        'linkedin_messages',
        'linkedin_message_attachments',
        'linkedin_shares',
        'linkedin_comments',
        'linkedin_reactions',
        'linkedin_rich_media',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'linkedin-import',
            errorMessage: 'Run does not describe a successful LinkedIn import.',
        );

        $run = $boundary['run'];
        $sourceLocator = $boundary['source_locator'];
        $scopeSnapshot = $boundary['scope_snapshot'];
        $archiveIds = $this->sourcePageHandoffSupport->resolveSourceSetIds('linkedin_archives', $sourceLocator);

        if ($archiveIds === []) {
            throw new InvalidArgumentException('No canonical LinkedIn rows were found for the requested run.');
        }

        $canonicalScope = [
            'source_locator' => $this->sourcePageHandoffSupport->normalizePath($sourceLocator),
            'accepted_root_paths' => $this->sourcePageHandoffSupport->normalizePaths(
                $scopeSnapshot['accepted_root_paths'] ?? []
            ),
            'tables' => self::CANONICAL_TABLES,
            'source_set_ids' => $archiveIds,
            'handoff_scope' => [
                'source_set_ids' => $archiveIds,
            ],
            'row_counts' => [
                'source_sets' => count($archiveIds),
                'profile_snapshots' => (int) DB::table('linkedin_profile_snapshots')->whereIn('linkedin_archive_id', $archiveIds)->count(),
                'positions' => (int) DB::table('linkedin_positions')->whereIn('first_seen_linkedin_archive_id', $archiveIds)->count(),
                'endorsements' => (int) DB::table('linkedin_endorsements')->whereIn('first_seen_linkedin_archive_id', $archiveIds)->count(),
                'conversations' => (int) DB::table('linkedin_conversations')->whereIn('first_seen_linkedin_archive_id', $archiveIds)->count(),
                'messages' => (int) DB::table('linkedin_messages')->whereIn('first_seen_linkedin_archive_id', $archiveIds)->count(),
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'linkedin',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
