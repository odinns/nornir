<?php

declare(strict_types=1);

use App\Actions\Gmail\TriageImportantMailAction;
use App\Services\Gmail\GmailApiClientInterface;
use App\Services\Gmail\GmailClientFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
    date_default_timezone_set('Europe/Copenhagen');
    $this->travelTo(CarbonImmutable::parse('2026-04-20 15:45:00', 'Europe/Copenhagen'));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

it('ranks urgent mail ahead of newsletter noise and builds a bounded Gmail query', function (): void {
    $capturedQuery = null;

    $messages = [
        buildGmailMessage([
            'id' => 'msg-urgent',
            'threadId' => 'thread-urgent',
            'labelIds' => ['INBOX', 'UNREAD', 'IMPORTANT'],
            'internalDate' => '1776691800000',
            'snippet' => 'Can you confirm today?',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Boss <boss@example.com>'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Need your answer today'],
                    ['name' => 'Date', 'value' => 'Mon, 20 Apr 2026 13:30:00 +0200'],
                ],
                'body' => ['data' => base64_encode('Can you confirm the plan today?'), 'size' => 30],
                'parts' => [],
            ],
        ]),
        buildGmailMessage([
            'id' => 'msg-newsletter',
            'threadId' => 'thread-newsletter',
            'labelIds' => ['INBOX', 'CATEGORY_PROMOTIONS'],
            'internalDate' => '1776605400000',
            'snippet' => 'Your weekly digest',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Updates <news@example.com>'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Weekly digest'],
                    ['name' => 'List-Unsubscribe', 'value' => '<mailto:unsubscribe@example.com>'],
                ],
                'body' => ['data' => base64_encode('A digest of things you did not ask for.'), 'size' => 40],
                'parts' => [],
            ],
        ]),
    ];

    $client = new class($messages, $capturedQuery) implements GmailApiClientInterface
    {
        /**
         * @param  list<array<string, mixed>>  $messages
         */
        public function __construct(
            private readonly array $messages,
            private mixed &$capturedQuery,
        ) {}

        public function getAccountEmail(): string
        {
            return 'odinn@example.com';
        }

        public function listMessages(string $query, ?string $pageToken = null): array
        {
            $this->capturedQuery = $query;

            return [
                'messages' => array_map(
                    static fn (array $message): array => [
                        'id' => $message['id'],
                        'threadId' => $message['threadId'],
                    ],
                    $this->messages,
                ),
                'nextPageToken' => null,
            ];
        }

        public function getMessage(string $messageId): array
        {
            foreach ($this->messages as $message) {
                if ($message['id'] === $messageId) {
                    return $message;
                }
            }

            throw new RuntimeException("Message {$messageId} not found.");
        }
    };

    app()->bind(GmailClientFactory::class, static fn (): GmailClientFactory => new class($client) extends GmailClientFactory
    {
        public function __construct(private readonly GmailApiClientInterface $client) {}

        public function make(string $credentialsPath, string $accountEmail): GmailApiClientInterface
        {
            return $this->client;
        }
    });

    $rulesPath = base_path('data/test-fixtures/gmail/priority-rules.json');
    File::ensureDirectoryExists(dirname($rulesPath));
    File::put($rulesPath, json_encode([
        'priority_senders' => ['boss@example.com'],
    ], JSON_THROW_ON_ERROR));

    $result = app(TriageImportantMailAction::class)(
        credentialsPath: '/tmp/fake-credentials.json',
        since: null,
        on: null,
        window: 'last 7 days',
        limit: 25,
        query: null,
        rulesPath: $rulesPath,
    );

    expect($capturedQuery)->toContain('after:2026/04/13');
    expect($capturedQuery)->toContain('before:2026/04/21');
    expect($result['matched_count'])->toBe(1);
    expect($result['items'][0]['message_id'])->toBe('msg-urgent');
    expect($result['items'][0]['urgency'])->toBe('today');
    expect($result['items'][0]['reason'])->toContain('priority sender');
    expect(DB::table('gmail_messages')->count())->toBe(0);
});
