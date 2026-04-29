<?php

declare(strict_types=1);

use App\Actions\Gmail\BuildGmailImportantEvidenceBundleAction;
use App\Actions\Import\ImportGmailAction;
use App\Models\ProvenanceLink;
use App\Models\Run;
use App\Models\RunArtifact;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/gmail'));
    File::deleteDirectory(base_path('data/reviews'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
    date_default_timezone_set('Europe/Copenhagen');
    $this->travelTo(CarbonImmutable::parse('2026-04-20 15:45:00', 'Europe/Copenhagen'));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('data/reviews'));
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

it('builds a full body important mail evidence bundle from canonical gmail rows', function (): void {
    bindFakeGmailClientForAccount([
        buildImportantEvidenceMessage(
            id: 'msg-high-old',
            threadId: 'thread-high-old',
            from: 'Boss <boss@example.com>',
            subject: 'Contract approval needed today',
            snippet: 'Can you approve the contract today?',
            plainBody: 'Full plain body: can you approve the contract today before 16:00?',
            htmlBody: '<p>Full <strong>HTML</strong> body: approve the contract today.</p>',
            internalDate: '1776508200000',
            labels: ['INBOX', 'UNREAD'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-tie-old',
            threadId: 'thread-tie-old',
            from: 'Colleague <colleague@example.com>',
            subject: 'Quick check',
            snippet: 'Can you reply?',
            plainBody: 'Can you reply when you have a moment?',
            htmlBody: '<p>Can you reply when you have a moment?</p>',
            internalDate: '1776421800000',
            labels: ['INBOX'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-tie-new',
            threadId: 'thread-tie-new',
            from: 'Client <client@example.com>',
            subject: 'Quick check',
            snippet: 'Can you reply?',
            plainBody: 'Can you reply when you have a moment?',
            htmlBody: '<p>Can you reply when you have a moment?</p>',
            internalDate: '1776677400000',
            labels: ['INBOX'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-newsletter',
            threadId: 'thread-newsletter',
            from: 'Digest <news@example.com>',
            subject: 'Weekly digest',
            snippet: 'A digest of things.',
            plainBody: 'A digest of things you did not ask for.',
            htmlBody: '<p>A digest of things you did not ask for.</p>',
            internalDate: '1776677400000',
            labels: ['INBOX', 'CATEGORY_PROMOTIONS'],
            headers: [
                ['name' => 'List-Unsubscribe', 'value' => '<mailto:unsubscribe@example.com>'],
            ],
        ),
    ], 'odinn@example.com');

    $importResult = app(ImportGmailAction::class)(makeGmailIntake('label:work')->dispatchPayload);
    $apiCallsAfterImport = app(FakeGmailApiClient::class)->getMessageCalls;
    $result = app(BuildGmailImportantEvidenceBundleAction::class)(
        runId: $importResult->run->id,
        limit: 50,
        rulesPath: null,
    );

    $bundle = decodeEvidenceBundle($result->path);

    expect($result->matchedCount)->toBe(3);
    expect(app(FakeGmailApiClient::class)->getMessageCalls)->toBe($apiCallsAfterImport);
    expect(array_keys($bundle))->toBe(expectedEvidenceBundleEnvelopeKeys());
    expect($bundle)->toMatchArray([
        'schema_version' => 1,
        'bundle_type' => 'gmail-important-mail',
        'source_type' => 'gmail',
        'source_run_id' => $importResult->run->id,
        'evidence_run_id' => $result->run->id,
        'account_email' => 'odinn@example.com',
        'query' => 'label:work',
        'selection' => [
            'mode' => 'important-mail-score',
            'limit' => 50,
            'matched_count' => 3,
        ],
    ]);
    expect($bundle['items'])->sequence(
        fn ($item) => $item->message_id->toBe('msg-high-old')->provenance_ref->toBe('gmail_messages:msg-high-old'),
        fn ($item) => $item->message_id->toBe('msg-tie-new')->provenance_ref->toBe('gmail_messages:msg-tie-new'),
        fn ($item) => $item->message_id->toBe('msg-tie-old')->provenance_ref->toBe('gmail_messages:msg-tie-old'),
    );
    expect(firstEvidenceBundleItem($bundle['items']))->toMatchArray([
        'thread_id' => 'thread-high-old',
        'from' => 'Boss <boss@example.com>',
        'to' => 'odinn@example.com',
        'cc' => '',
        'subject' => 'Contract approval needed today',
        'labels' => ['INBOX', 'UNREAD'],
        'snippet' => 'Can you approve the contract today?',
        'body_plain' => 'Full plain body: can you approve the contract today before 16:00?',
        'body_html' => '<p>Full <strong>HTML</strong> body: approve the contract today.</p>',
        'provenance_ref' => 'gmail_messages:msg-high-old',
    ]);

    expect($result->run->parent_run_id)->toBe($importResult->run->id);
    expect($result->run->subsystem)->toBe('muninn');
    expect($result->run->operation)->toBe('gmail-important-evidence-bundle');
    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);

    $artifact = RunArtifact::query()->where('run_id', $result->run->id)->firstOrFail();
    expect($artifact->artifact_kind)->toBe('gmail-important-evidence-bundle');
    expect($artifact->classification)->toBe('review');
    expect($artifact->locator)->toBe($result->path);

    expect(ProvenanceLink::query()->where('run_id', $result->run->id)->orderBy('id')->pluck('evidence_ref')->all())->toBe([
        'gmail_messages:msg-high-old',
        'gmail_messages:msg-tie-new',
        'gmail_messages:msg-tie-old',
    ]);
});

it('excludes legacy imported marketing and list mail from canonical evidence bundles', function (): void {
    bindFakeGmailClientForAccount([
        buildImportantEvidenceMessage(
            id: 'msg-ebay',
            threadId: 'thread-ebay',
            from: 'eBay <reply3@reply3.ebay.com>',
            subject: 'Top 10 deals ending today?',
            snippet: 'Voucher deals picked for you.',
            plainBody: 'Top 10 deals ending today? Use this voucher before midnight. Unsubscribe at any time.',
            htmlBody: '<p>Top 10 deals ending today? Use this voucher before midnight.</p>',
            internalDate: '1776691800000',
            labels: ['INBOX'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-thinkgeek',
            threadId: 'thread-thinkgeek',
            from: 'ThinkGeek Newsletter <newsletter@thinkgeek.com>',
            subject: 'ThinkGeek newsletter: new deals',
            snippet: 'View in browser for the full digest.',
            plainBody: 'This week in gadgets. View in browser or unsubscribe.',
            htmlBody: '<p>This week in gadgets. View in browser or unsubscribe.</p>',
            internalDate: '1776691800000',
            labels: ['INBOX'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-weekend',
            threadId: 'thread-weekend',
            from: 'Rejsefeber <members@rejsefeber.dk>',
            subject: 'Weekendtilbud fra AOK og Rejsefeber?',
            snippet: 'Tilbud på rejser og weekendoplevelser.',
            plainBody: 'Weekendtilbud fra AOK og Rejsefeber? Afmeld nyhedsbrev nederst.',
            htmlBody: '<p>Weekendtilbud fra AOK og Rejsefeber? Afmeld nyhedsbrev nederst.</p>',
            internalDate: '1776691800000',
            labels: ['INBOX'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-it-jobbank',
            threadId: 'thread-it-jobbank',
            from: 'IT-Jobbank mailrobot <mailrobot@it-jobbank.dk>',
            subject: 'Karrieremail: nye jobs til dig',
            snippet: 'Are you ready to apply today?',
            plainBody: 'Are you ready to apply today? Afmeld karrieremail nederst.',
            htmlBody: '<p>Are you ready to apply today? Afmeld karrieremail nederst.</p>',
            internalDate: '1776691800000',
            labels: ['INBOX'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-human',
            threadId: 'thread-human',
            from: 'Human <human@example.com>',
            subject: 'Confirm today',
            snippet: 'Can you confirm today?',
            plainBody: 'Can you confirm the plan today?',
            htmlBody: '<p>Can you confirm the plan today?</p>',
            internalDate: '1776691800000',
            labels: ['INBOX'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-admin',
            threadId: 'thread-admin',
            from: 'Admin <admin@example.com>',
            subject: 'Invoice signature needed',
            snippet: 'Please sign the invoice before 16:00.',
            plainBody: 'Please sign the invoice before 16:00.',
            htmlBody: '<p>Please sign the invoice before 16:00.</p>',
            internalDate: '1776691800000',
            labels: ['INBOX'],
        ),
    ], 'odinn@example.com');

    $importResult = app(ImportGmailAction::class)(makeGmailIntake('label:legacy-marketing')->dispatchPayload);
    $result = app(BuildGmailImportantEvidenceBundleAction::class)(
        runId: $importResult->run->id,
        limit: 50,
        rulesPath: null,
    );

    $bundle = decodeEvidenceBundle($result->path);
    $messageIds = array_column($bundle['items'], 'message_id');
    sort($messageIds);

    expect($result->matchedCount)->toBe(2);
    expect($messageIds)->toBe([
        'msg-admin',
        'msg-human',
    ]);
});

it('writes an empty bundle when canonical gmail rows exist but none score as important', function (): void {
    bindFakeGmailClientForAccount([
        buildImportantEvidenceMessage(
            id: 'msg-calm',
            threadId: 'thread-calm',
            from: 'Calm Sender <sender@example.com>',
            subject: 'FYI',
            snippet: 'A calm update.',
            plainBody: 'A calm update with no question or action requested.',
            htmlBody: '<p>A calm update with no question or action requested.</p>',
            internalDate: '1776677400000',
            labels: ['INBOX'],
        ),
    ], 'odinn@example.com');

    $importResult = app(ImportGmailAction::class)(makeGmailIntake('label:calm')->dispatchPayload);
    $result = app(BuildGmailImportantEvidenceBundleAction::class)($importResult->run->id);
    $bundle = decodeEvidenceBundle($result->path);

    expect($result->matchedCount)->toBe(0);
    expect(array_keys($bundle))->toBe(expectedEvidenceBundleEnvelopeKeys());
    expect($bundle['schema_version'])->toBe(1);
    expect($bundle['selection'])->toMatchArray([
        'mode' => 'important-mail-score',
        'limit' => 50,
        'matched_count' => 0,
    ]);
    expect($bundle['items'])->toBe([]);
    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(ProvenanceLink::query()->where('run_id', $result->run->id)->count())->toBe(0);
});

it('refreshes provenance when rebuilding the same source run with a smaller limit', function (): void {
    bindFakeGmailClientForAccount([
        buildImportantEvidenceMessage(
            id: 'msg-001',
            threadId: 'thread-001',
            from: 'Sender One <one@example.com>',
            subject: 'Question today',
            snippet: 'Can you respond today?',
            plainBody: 'Can you respond today?',
            htmlBody: '<p>Can you respond today?</p>',
            internalDate: '1776677400000',
            labels: ['INBOX'],
        ),
        buildImportantEvidenceMessage(
            id: 'msg-002',
            threadId: 'thread-002',
            from: 'Sender Two <two@example.com>',
            subject: 'Another question today',
            snippet: 'Can you respond today?',
            plainBody: 'Can you respond today?',
            htmlBody: '<p>Can you respond today?</p>',
            internalDate: '1776591000000',
            labels: ['INBOX'],
        ),
    ], 'odinn@example.com');

    $importResult = app(ImportGmailAction::class)(makeGmailIntake('label:rerun')->dispatchPayload);

    app(BuildGmailImportantEvidenceBundleAction::class)(
        runId: $importResult->run->id,
        limit: 2,
        rulesPath: null,
    );

    $result = app(BuildGmailImportantEvidenceBundleAction::class)(
        runId: $importResult->run->id,
        limit: 1,
        rulesPath: null,
    );
    $bundle = decodeEvidenceBundle($result->path);

    expect($result->matchedCount)->toBe(1);
    expect($bundle['selection'])->toMatchArray([
        'limit' => 1,
        'matched_count' => 1,
    ]);
    expect(RunArtifact::query()->where('run_id', $result->run->id)->firstOrFail()->metadata)->toMatchArray([
        'limit' => 1,
        'matched_count' => 1,
    ]);
    expect(ProvenanceLink::query()->where('run_id', $result->run->id)->orderBy('id')->pluck('evidence_ref')->all())->toBe([
        'gmail_messages:msg-001',
    ]);
});

it('rejects runs that are not successful gmail imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'import',
        'operation' => 'twitter-import',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => [
            'source_locator' => '/tmp/twitter.zip',
            'scope_snapshot' => [],
        ],
        'idempotency_key' => 'twitter-import:test',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildGmailImportantEvidenceBundleAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful Gmail import.');
});

it('rejects failed gmail import runs', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'import',
        'operation' => 'gmail-import',
        'status' => Run::STATUS_FAILED,
        'input_scope' => [
            'source_locator' => createGmailCredentialsFixture(),
            'scope_snapshot' => ['query' => 'from:me'],
        ],
        'idempotency_key' => 'gmail-import:failed-test',
        'started_at' => now(),
        'finished_at' => now(),
        'failure_summary' => 'Nope.',
    ]);

    expect(fn () => app(BuildGmailImportantEvidenceBundleAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful Gmail import.');
});

it('rejects gmail import runs with no canonical message rows', function (): void {
    bindFakeGmailClientForAccount([], 'empty@example.com');

    $importResult = app(ImportGmailAction::class)(makeGmailIntake('label:empty')->dispatchPayload);

    expect(fn () => app(BuildGmailImportantEvidenceBundleAction::class)($importResult->run->id))
        ->toThrow(InvalidArgumentException::class, 'No canonical Gmail rows were found for the requested run.');
});

/**
 * @param  list<string>  $labels
 * @param  list<array{name:string, value:string}>  $headers
 * @return array<string, mixed>
 */
function buildImportantEvidenceMessage(
    string $id,
    string $threadId,
    string $from,
    string $subject,
    string $snippet,
    string $plainBody,
    string $htmlBody,
    string $internalDate,
    array $labels,
    array $headers = [],
): array {
    return buildGmailMessage([
        'id' => $id,
        'threadId' => $threadId,
        'labelIds' => $labels,
        'snippet' => $snippet,
        'internalDate' => $internalDate,
        'payload' => [
            'mimeType' => 'multipart/alternative',
            'headers' => array_merge([
                ['name' => 'From', 'value' => $from],
                ['name' => 'To', 'value' => 'odinn@example.com'],
                ['name' => 'Subject', 'value' => $subject],
            ], $headers),
            'body' => ['size' => 0],
            'parts' => [
                [
                    'mimeType' => 'text/plain',
                    'body' => ['data' => base64_encode($plainBody), 'size' => strlen($plainBody)],
                    'parts' => [],
                ],
                [
                    'mimeType' => 'text/html',
                    'body' => ['data' => base64_encode($htmlBody), 'size' => strlen($htmlBody)],
                    'parts' => [],
                ],
            ],
        ],
    ]);
}

/**
 * @return array{schema_version:1, bundle_type:string, source_type:string, source_run_id:int, evidence_run_id:int, generated_at:string, account_email:string, source_set_ids:list<int>, query:string, selection:array{mode:string, limit:int, matched_count:int}, items:list<array{message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, received_at:string, urgency:string, reason:string, next_action:string, confidence:float, labels:list<string>, snippet:string, body_plain:string, body_html:string, provenance_ref:string}>}
 */
function decodeEvidenceBundle(string $path): array
{
    $decoded = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException('Evidence bundle JSON did not decode to an object.');
    }

    /** @var array{schema_version:1, bundle_type:string, source_type:string, source_run_id:int, evidence_run_id:int, generated_at:string, account_email:string, source_set_ids:list<int>, query:string, selection:array{mode:string, limit:int, matched_count:int}, items:list<array{message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, received_at:string, urgency:string, reason:string, next_action:string, confidence:float, labels:list<string>, snippet:string, body_plain:string, body_html:string, provenance_ref:string}>} $decoded */
    return $decoded;
}

/**
 * @return list<string>
 */
function expectedEvidenceBundleEnvelopeKeys(): array
{
    return [
        'schema_version',
        'bundle_type',
        'source_type',
        'source_run_id',
        'evidence_run_id',
        'generated_at',
        'account_email',
        'source_set_ids',
        'query',
        'selection',
        'items',
    ];
}

/**
 * @param  list<array{message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, received_at:string, urgency:string, reason:string, next_action:string, confidence:float, labels:list<string>, snippet:string, body_plain:string, body_html:string, provenance_ref:string}>  $items
 * @return array{message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, received_at:string, urgency:string, reason:string, next_action:string, confidence:float, labels:list<string>, snippet:string, body_plain:string, body_html:string, provenance_ref:string}
 */
function firstEvidenceBundleItem(array $items): array
{
    if ($items === []) {
        throw new RuntimeException('Expected at least one evidence bundle item.');
    }

    return $items[0];
}
