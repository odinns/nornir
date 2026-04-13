<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildGmailSourcePageHandoffAction
{
    private const string TABLE_ACCOUNTS = 'gmail_accounts';

    private const string TABLE_THREADS = 'gmail_threads';

    private const string TABLE_MESSAGES = 'gmail_messages';

    private const array CANONICAL_TABLES = [
        self::TABLE_ACCOUNTS,
        self::TABLE_THREADS,
        self::TABLE_MESSAGES,
        'gmail_message_labels',
        'gmail_attachments',
    ];

    public function __construct(
        private readonly SourcePageHandoffSupport $sourcePageHandoffSupport,
    ) {}

    public function __invoke(int $runId): WikiCompilationHandoffData
    {
        $boundary = $this->sourcePageHandoffSupport->resolveRunBoundary(
            runId: $runId,
            operation: 'gmail-import',
            errorMessage: 'Run does not describe a successful Gmail import.',
        );

        $run = $boundary['run'];
        $scopeSnapshot = $boundary['scope_snapshot'];

        $account = DB::table(self::TABLE_ACCOUNTS)->orderBy('id')->first();

        if ($account === null) {
            throw new InvalidArgumentException('No canonical Gmail rows were found for the requested run.');
        }

        $accountEmail = (string) $account->account_email;

        $accountId = (int) $account->id;
        $threadCount = (int) DB::table(self::TABLE_THREADS)->where('gmail_account_id', $accountId)->count();
        $messageCount = (int) DB::table(self::TABLE_MESSAGES)
            ->whereIn('gmail_thread_id', DB::table(self::TABLE_THREADS)->where('gmail_account_id', $accountId)->pluck('id'))
            ->count();

        $canonicalScope = [
            'account_email' => $accountEmail,
            'query' => $scopeSnapshot['query'] ?? null,
            'tables' => self::CANONICAL_TABLES,
            'row_counts' => [
                'threads' => $threadCount,
                'messages' => $messageCount,
            ],
        ];

        return new WikiCompilationHandoffData(
            sourceType: 'gmail',
            handoffType: 'source-pages',
            owningRunId: $run->id,
            canonicalScope: $canonicalScope,
        );
    }
}
