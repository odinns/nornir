<?php

declare(strict_types=1);

use App\Services\Gmail\GmailApiClientInterface;
use Illuminate\Support\Facades\File;

class FakeGmailApiClient implements GmailApiClientInterface
{
    /** @var list<array<string, mixed>> */
    private array $messages;

    /** @param list<array<string, mixed>> $messages */
    public function __construct(array $messages = [])
    {
        $this->messages = $messages;
    }

    public function getAccountEmail(): string
    {
        return 'test@example.com';
    }

    /**
     * {@inheritDoc}
     */
    public function listMessages(string $query, ?string $pageToken = null): array
    {
        return [
            'messages' => array_map(
                static fn (array $m): array => array_filter(
                    ['id' => $m['id'] ?? null, 'threadId' => $m['threadId'] ?? null],
                    static fn (mixed $v): bool => $v !== null,
                ),
                $this->messages,
            ),
            'nextPageToken' => null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(string $messageId): array
    {
        foreach ($this->messages as $message) {
            if (($message['id'] ?? null) === $messageId) {
                return $message;
            }
        }

        throw new RuntimeException("FakeGmailApiClient: message {$messageId} not found");
    }
}

/**
 * Create a temporary Gmail credentials JSON file and return its path.
 */
function createGmailCredentialsFixture(string $label = 'gmail-test'): string
{
    $dir = base_path('data/test-fixtures/gmail/'.$label);
    File::ensureDirectoryExists($dir);

    $path = $dir.'/credentials.json';

    File::put($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'test-project',
        'private_key_id' => 'key-id',
        'private_key' => '-----BEGIN RSA PRIVATE KEY-----\nfake\n-----END RSA PRIVATE KEY-----',
        'client_email' => 'test@test-project.iam.gserviceaccount.com',
        'client_id' => '123456789',
        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], JSON_PRETTY_PRINT));

    return $path;
}

/**
 * Build a minimal Gmail message fixture in the format the API returns.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function buildGmailMessage(array $overrides = []): array
{
    $defaults = [
        'id' => 'msg-001',
        'threadId' => 'thread-001',
        'labelIds' => ['INBOX'],
        'snippet' => 'Hello world snippet',
        'internalDate' => '1712000000000',
        'payload' => [
            'mimeType' => 'text/plain',
            'headers' => [
                ['name' => 'From', 'value' => 'sender@example.com'],
                ['name' => 'To', 'value' => 'recipient@example.com'],
                ['name' => 'Subject', 'value' => 'Test subject'],
                ['name' => 'Date', 'value' => 'Thu, 01 Apr 2026 10:00:00 +0000'],
            ],
            'body' => [
                'data' => base64_encode('Hello, this is the email body.'),
                'size' => 30,
            ],
            'parts' => [],
        ],
    ];

    return array_replace_recursive($defaults, $overrides);
}
