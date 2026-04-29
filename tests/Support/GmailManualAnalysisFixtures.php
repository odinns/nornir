<?php

declare(strict_types=1);

use App\Models\Run;
use App\Models\RunArtifact;
use Illuminate\Support\Facades\File;

function cleanupGmailManualAnalysisTestFiles(): void
{
    $artifactPaths = RunArtifact::query()
        ->whereIn('artifact_kind', ['manual-analysis-note', 'muninn-biography-candidates'])
        ->pluck('locator')
        ->filter(static fn (mixed $locator): bool => is_string($locator))
        ->all();

    File::delete(array_merge([
        base_path('data/reviews/gmail-important-evidence-run-manual-analysis-test.json'),
        base_path('data/reviews/operator-notes/custom-manual-note.md'),
    ], $artifactPaths));
}

/**
 * @return array{source: Run, evidence: Run}
 */
function createManualAnalysisBundleRuns(): array
{
    $sourceRun = Run::query()->create([
        'subsystem' => 'import',
        'operation' => 'gmail-import',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => [
            'source_locator' => base_path('data/test-fixtures/gmail/credentials.json'),
            'scope_snapshot' => ['query' => 'label:important'],
        ],
        'idempotency_key' => 'gmail-import:manual-analysis-test',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $evidenceRun = Run::query()->create([
        'parent_run_id' => $sourceRun->id,
        'subsystem' => 'muninn',
        'operation' => 'gmail-important-evidence-bundle',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => [
            'source_type' => 'gmail',
            'source_run_id' => $sourceRun->id,
        ],
        'idempotency_key' => 'gmail-important-evidence-bundle:manual-analysis-test',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    return [
        'source' => $sourceRun,
        'evidence' => $evidenceRun,
    ];
}

/**
 * @return array{schema_version:1, bundle_type:string, source_type:string, source_run_id:int, evidence_run_id:int, generated_at:string, account_email:string, source_set_ids:list<int>, query:string, selection:array{mode:string, limit:int, matched_count:int}, items:list<array{message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, received_at:string, urgency:string, reason:string, next_action:string, confidence:float, labels:list<string>, snippet:string, body_plain:string, body_html:string, provenance_ref:string}>}
 */
function gmailManualAnalysisBundleDefaults(): array
{
    return [
        'schema_version' => 1,
        'bundle_type' => 'gmail-important-mail',
        'source_type' => 'gmail',
        'source_run_id' => 1,
        'evidence_run_id' => 2,
        'generated_at' => '2026-04-20T15:45:00+02:00',
        'account_email' => 'odinn@example.com',
        'source_set_ids' => [10],
        'query' => 'label:important',
        'selection' => [
            'mode' => 'important-mail-score',
            'limit' => 50,
            'matched_count' => 0,
        ],
        'items' => [],
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function writeGmailManualAnalysisBundle(array $overrides = []): string
{
    return writeRawGmailManualAnalysisBundle(array_replace_recursive(gmailManualAnalysisBundleDefaults(), $overrides));
}

/**
 * @param  array<string, mixed>  $bundle
 */
function writeRawGmailManualAnalysisBundle(array $bundle): string
{
    $path = base_path('data/reviews/gmail-important-evidence-run-manual-analysis-test.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return $path;
}

/**
 * @return array{message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, received_at:string, urgency:string, reason:string, next_action:string, confidence:float, labels:list<string>, snippet:string, body_plain:string, body_html:string, provenance_ref:string}
 */
function gmailManualAnalysisBundleItem(
    string $messageId,
    string $threadId,
    string $subject,
    string $receivedAt,
): array {
    return [
        'message_id' => $messageId,
        'thread_id' => $threadId,
        'from' => 'Sender <sender@example.com>',
        'to' => 'odinn@example.com',
        'cc' => '',
        'subject' => $subject,
        'received_at' => $receivedAt,
        'urgency' => 'high',
        'reason' => 'Direct question needing review.',
        'next_action' => 'Decide whether this belongs in the biography timeline.',
        'confidence' => 0.91,
        'labels' => ['INBOX', 'IMPORTANT'],
        'snippet' => 'Can you review this?',
        'body_plain' => 'Can you review this before Friday?',
        'body_html' => '<p>Can you review this before Friday?</p>',
        'provenance_ref' => 'gmail_messages:'.$messageId,
    ];
}
