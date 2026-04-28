<?php

declare(strict_types=1);

use App\Actions\Gmail\TriageImportantMailAction;
use App\Models\GmailMessage;
use App\Services\Gmail\GmailApiClientInterface;
use App\Services\Gmail\GmailClientFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

class GmailTriageQueryCapture
{
    public ?string $query = null;
}

/**
 * @param  list<array<string, mixed>>  $messages
 */
function bindGmailTriageClient(array $messages, ?GmailTriageQueryCapture $capture = null): void
{
    $capture ??= new GmailTriageQueryCapture;

    $client = new readonly class($messages, $capture) implements GmailApiClientInterface
    {
        /**
         * @param  list<array<string, mixed>>  $messages
         */
        public function __construct(
            private array $messages,
            private GmailTriageQueryCapture $capture,
        ) {}

        public function getAccountEmail(): string
        {
            return 'odinn@example.com';
        }

        public function refreshAuthentication(): void {}

        public function listMessages(string $query, ?string $pageToken = null): array
        {
            $this->capture->query = $query;

            return [
                'messages' => array_map(
                    static fn (array $message): array => [
                        'id' => (string) ($message['id'] ?? ''),
                        'threadId' => (string) ($message['threadId'] ?? ''),
                    ],
                    $this->messages,
                ),
                'nextPageToken' => null,
            ];
        }

        public function getMessage(string $messageId): array
        {
            foreach ($this->messages as $message) {
                if (($message['id'] ?? null) === $messageId) {
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
}

it('ranks urgent mail ahead of newsletter noise and builds a bounded Gmail query', function (): void {
    $queryCapture = new GmailTriageQueryCapture;

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

    bindGmailTriageClient($messages, $queryCapture);

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

    expect($queryCapture->query)->toContain('after:2026/04/13');
    expect($queryCapture->query)->toContain('before:2026/04/21');
    expect($result['matched_count'])->toBe(1);
    $item = firstGmailTriageItem($result['items']);
    expect($item['message_id'])->toBe('msg-urgent');
    expect($item['urgency'])->toBe('today');
    expect($item['reason'])->toContain('priority sender');
    expect(GmailMessage::query()->count())->toBe(0);
});

it('ignores bulk alerts that only look urgent in the subject line', function (): void {
    bindGmailTriageClient([
        buildGmailMessage([
            'id' => 'msg-linkedin',
            'threadId' => 'thread-linkedin',
            'labelIds' => ['INBOX', 'IMPORTANT'],
            'internalDate' => '1776691800000',
            'snippet' => 'New roles picked for you.',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'LinkedIn Job Alerts <jobalerts-noreply@linkedin.com>'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Apply now to Senior Software Engineer?'],
                    ['name' => 'List-Unsubscribe', 'value' => '<mailto:unsubscribe@linkedin.com>'],
                ],
                'body' => ['data' => base64_encode('Fresh jobs matching your saved search.'), 'size' => 39],
                'parts' => [],
            ],
        ]),
    ]);

    $result = app(TriageImportantMailAction::class)(
        credentialsPath: '/tmp/fake-credentials.json',
        since: null,
        on: null,
        window: 'last 7 days',
        limit: 25,
        query: null,
        rulesPath: null,
    );

    expect($result['matched_count'])->toBe(0);
});

it('treats ase mail as important by default', function (): void {
    bindGmailTriageClient([
        buildGmailMessage([
            'id' => 'msg-ase',
            'threadId' => 'thread-ase',
            'labelIds' => ['INBOX', 'UNREAD'],
            'internalDate' => '1776691800000',
            'snippet' => 'Please update your details today.',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Ase Job- og karriereplatform <noreply@ase.dk>'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Please confirm your profile today'],
                ],
                'body' => ['data' => base64_encode('Can you confirm your profile details today?'), 'size' => 42],
                'parts' => [],
            ],
        ]),
    ]);

    $result = app(TriageImportantMailAction::class)(
        credentialsPath: '/tmp/fake-credentials.json',
        since: null,
        on: null,
        window: 'last 7 days',
        limit: 25,
        query: null,
        rulesPath: null,
    );

    expect($result['matched_count'])->toBe(1);
    $item = firstGmailTriageItem($result['items']);
    expect($item['message_id'])->toBe('msg-ase');
    expect($item['reason'])->toContain('priority domain');
});

it('treats haveforeningenkildebo mail as important by default', function (): void {
    bindGmailTriageClient([
        buildGmailMessage([
            'id' => 'msg-kildebo',
            'threadId' => 'thread-kildebo',
            'labelIds' => ['INBOX'],
            'internalDate' => '1776691800000',
            'snippet' => 'Can you look at this before the meeting?',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Kildebo <haveforeningenkildebo@gmail.com>'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Question before the meeting'],
                ],
                'body' => ['data' => base64_encode('Can you look at this before the meeting today?'), 'size' => 46],
                'parts' => [],
            ],
        ]),
    ]);

    $result = app(TriageImportantMailAction::class)(
        credentialsPath: '/tmp/fake-credentials.json',
        since: null,
        on: null,
        window: 'last 7 days',
        limit: 25,
        query: null,
        rulesPath: null,
    );

    expect($result['matched_count'])->toBe(1);
    $item = firstGmailTriageItem($result['items']);
    expect($item['message_id'])->toBe('msg-kildebo');
    expect($item['reason'])->toContain('priority sender');
});

it('does not apply a default inspection limit', function (): void {
    bindGmailTriageClient(array_map(
        static fn (int $index): array => buildGmailMessage([
            'id' => 'msg-'.$index,
            'threadId' => 'thread-'.$index,
            'internalDate' => '1776691800000',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Bulk '.$index.' <bulk'.$index.'@example.com>'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Status update '.$index],
                ],
                'body' => ['data' => base64_encode('A calm message with no action needed.'), 'size' => 34],
                'parts' => [],
            ],
        ]),
        range(1, 40),
    ));

    $result = app(TriageImportantMailAction::class)(
        credentialsPath: '/tmp/fake-credentials.json',
        since: null,
        on: null,
        window: 'last 7 days',
        limit: null,
        query: null,
        rulesPath: null,
    );

    expect($result['inspected_count'])->toBe(40);
});

/**
 * @param  list<array{message_id: string, thread_id: string, from: string, subject: string, received_at: string, urgency: string, reason: string, next_action: string, confidence: float}>  $items
 * @return array{message_id: string, thread_id: string, from: string, subject: string, received_at: string, urgency: string, reason: string, next_action: string, confidence: float}
 */
function firstGmailTriageItem(array $items): array
{
    if ($items === []) {
        throw new RuntimeException('Expected at least one Gmail triage item.');
    }

    return $items[0];
}
