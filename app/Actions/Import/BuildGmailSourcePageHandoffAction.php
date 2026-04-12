<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\SourcePageHandoffSupport;
use App\Data\Import\WikiCompilationHandoffData;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildGmailSourcePageHandoffAction
{
    private const array CANONICAL_TABLES = [
        'gmail_accounts',
        'gmail_threads',
        'gmail_messages',
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
        $accountEmail = (string) ($scopeSnapshot['account_email'] ?? '');

        $account = DB::table('gmail_accounts')->where('account_email', $accountEmail)->first();

        if ($account === null) {
            throw new InvalidArgumentException('No canonical Gmail rows were found for the requested run.');
        }

        $accountId = (int) $account->id;
        $threadCount = (int) DB::table('gmail_threads')->where('gmail_account_id', $accountId)->count();
        $messageCount = (int) DB::table('gmail_messages')
            ->whereIn('gmail_thread_id', DB::table('gmail_threads')->where('gmail_account_id', $accountId)->pluck('id'))
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
