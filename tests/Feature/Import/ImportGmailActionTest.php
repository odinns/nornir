<?php

declare(strict_types=1);

use App\Actions\Import\ImportGmailAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Data\Intake\RecordIntakeResultData;
use App\Models\Run;
use App\Services\Gmail\GmailApiClientInterface;
use App\Services\Gmail\GmailClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/gmail'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

/**
 * @param  list<array<string, mixed>>  $messages
 */
function bindFakeGmailClient(array $messages): void
{
    $fake = new FakeGmailApiClient($messages);

    app()->bind(GmailClientFactory::class, static fn (): GmailClientFactory => new class($fake) extends GmailClientFactory
    {
        public function __construct(private readonly GmailApiClientInterface $client) {}

        public function make(string $credentialsPath, string $accountEmail): GmailApiClientInterface
        {
            return $this->client;
        }
    });
}

function makeGmailIntake(string $query = 'from:me'): RecordIntakeResultData
{
    $credentialsPath = createGmailCredentialsFixture();

    return app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'gmail',
        accessMode: 'api',
        sourceLocator: $credentialsPath,
        scopeSnapshot: [
            'query' => $query,
        ],
        importerOptions: [],
    ));
}

it('imports gmail messages into canonical tables and records a succeeded run', function (): void {
    bindFakeGmailClient([
        buildGmailMessage([
            'id' => 'msg-001',
            'threadId' => 'thread-001',
            'labelIds' => ['INBOX'],
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'sender@example.com'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Hello'],
                ],
                'body' => ['data' => base64_encode('Body text'), 'size' => 9],
                'parts' => [],
            ],
        ]),
        buildGmailMessage([
            'id' => 'msg-002',
            'threadId' => 'thread-001',
            'labelIds' => ['INBOX', 'UNREAD'],
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'odinn@example.com'],
                    ['name' => 'To', 'value' => 'sender@example.com'],
                    ['name' => 'Subject', 'value' => 'Re: Hello'],
                ],
                'body' => ['data' => base64_encode('Reply text'), 'size' => 10],
                'parts' => [],
            ],
        ]),
    ]);

    $intake = makeGmailIntake();
    $result = app(ImportGmailAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('gmail_accounts')->count())->toBe(1);
    expect(DB::table('gmail_threads')->count())->toBe(1);
    expect(DB::table('gmail_messages')->count())->toBe(2);
    expect(DB::table('gmail_message_labels')->count())->toBe(3); // INBOX×2 + UNREAD×1
    expect(DB::table('gmail_attachments')->count())->toBe(0);

    $account = DB::table('gmail_accounts')->first();
    expect($account->account_email)->toBe('test@example.com');
    expect($account->access_mode)->toBe('api');

    expect($result->summary['messages'])->toBe(2);
    expect($result->summary['threads'])->toBe(1);
    expect($result->summary['inserted_messages'])->toBe(2);
    expect($result->summary['reobserved_messages'])->toBe(0);
});

it('is idempotent on rerun with the same messages', function (): void {
    bindFakeGmailClient([buildGmailMessage(['id' => 'msg-001', 'threadId' => 'thread-001'])]);

    $intake = makeGmailIntake();
    app(ImportGmailAction::class)($intake->dispatchPayload);
    expect(DB::table('gmail_messages')->count())->toBe(1);

    $intake2 = makeGmailIntake();
    $result2 = app(ImportGmailAction::class)($intake2->dispatchPayload);

    expect($result2->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('gmail_messages')->count())->toBe(1);
    expect($result2->summary['inserted_messages'])->toBe(0);
    expect($result2->summary['reobserved_messages'])->toBe(1);
});

it('adds new messages on a subsequent run without losing prior ones', function (): void {
    bindFakeGmailClient([buildGmailMessage(['id' => 'msg-001', 'threadId' => 'thread-001'])]);
    $intake = makeGmailIntake();
    app(ImportGmailAction::class)($intake->dispatchPayload);
    expect(DB::table('gmail_messages')->count())->toBe(1);

    bindFakeGmailClient([
        buildGmailMessage(['id' => 'msg-001', 'threadId' => 'thread-001']),
        buildGmailMessage(['id' => 'msg-002', 'threadId' => 'thread-001']),
    ]);

    $intake2 = makeGmailIntake();
    $result = app(ImportGmailAction::class)($intake2->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('gmail_messages')->count())->toBe(2);
    expect($result->summary['inserted_messages'])->toBe(1);
    expect($result->summary['reobserved_messages'])->toBe(1);
});

it('succeeds with an empty query result and records zero messages', function (): void {
    bindFakeGmailClient([]);

    $intake = makeGmailIntake();
    $result = app(ImportGmailAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('gmail_messages')->count())->toBe(0);
    expect($result->summary['messages'])->toBe(0);
});

it('records attachment metadata without downloading binaries', function (): void {
    bindFakeGmailClient([
        buildGmailMessage([
            'id' => 'msg-001',
            'threadId' => 'thread-001',
            'payload' => [
                'mimeType' => 'multipart/mixed',
                'headers' => [
                    ['name' => 'From', 'value' => 'sender@example.com'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'File attached'],
                ],
                'body' => ['size' => 0, 'data' => ''],
                'parts' => [
                    [
                        'mimeType' => 'text/plain',
                        'body' => ['data' => base64_encode('See attached'), 'size' => 12],
                        'headers' => [],
                        'parts' => [],
                    ],
                    [
                        'mimeType' => 'application/pdf',
                        'filename' => 'report.pdf',
                        'body' => ['attachmentId' => 'attach-abc', 'size' => 204800],
                        'headers' => [],
                        'parts' => [],
                    ],
                ],
            ],
        ]),
    ]);

    $intake = makeGmailIntake();
    $result = app(ImportGmailAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(DB::table('gmail_attachments')->count())->toBe(1);

    $attachment = DB::table('gmail_attachments')->first();
    expect($attachment->filename)->toBe('report.pdf');
    expect($attachment->mime_type)->toBe('application/pdf');
    expect($attachment->size_bytes)->toBe(204800);
    expect($attachment->attachment_id)->toBe('attach-abc');
});

it('records a failed run when a message is malformed', function (): void {
    $messages = [
        ['id' => '', 'threadId' => 'thread-001'], // empty id
    ];

    bindFakeGmailClient($messages);

    $intake = makeGmailIntake();

    expect(fn () => app(ImportGmailAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class);

    expect(DB::table('runs')->where('status', Run::STATUS_FAILED)->count())->toBe(1);
});
