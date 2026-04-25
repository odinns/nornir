<?php

declare(strict_types=1);

use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Data\Intake\RecordIntakeResultData;
use App\Services\Gmail\GmailApiClientInterface;
use App\Services\Gmail\GmailClientFactory;
use Illuminate\Support\Facades\File;

class FakeGmailApiClient implements GmailApiClientInterface
{
    public int $getMessageCalls = 0;

    public int $refreshAuthenticationCalls = 0;

    /** @param list<array<string, mixed>> $messages */
    public function __construct(
        private readonly array $messages = [],
        private readonly string $accountEmail = 'test@example.com',
        /** @var array<string, int> */
        private array $authFailuresByMessageId = []
    ) {}

    public function getAccountEmail(): string
    {
        return $this->accountEmail;
    }

    /**
     * {@inheritDoc}
     */
    public function listMessages(string $query, ?string $pageToken = null): array
    {
        return [
            'messages' => array_map(
                static fn (array $m): array => [
                    'id' => (string) ($m['id'] ?? ''),
                    'threadId' => (string) ($m['threadId'] ?? ''),
                ],
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
        $this->getMessageCalls++;

        if (($this->authFailuresByMessageId[$messageId] ?? 0) > 0) {
            $this->authFailuresByMessageId[$messageId]--;

            throw new RuntimeException('Invalid Credentials', 401);
        }

        foreach ($this->messages as $message) {
            if (($message['id'] ?? null) === $messageId) {
                return $message;
            }
        }

        throw new RuntimeException("FakeGmailApiClient: message {$messageId} not found");
    }

    public function refreshAuthentication(): void
    {
        $this->refreshAuthenticationCalls++;
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
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return $path;
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

/**
 * @param  list<array<string, mixed>>  $messages
 * @param  array<string, int>  $authFailuresByMessageId
 */
function bindFakeGmailClient(array $messages, array $authFailuresByMessageId = []): void
{
    bindFakeGmailClientForAccount($messages, 'test@example.com', $authFailuresByMessageId);
}

/**
 * @param  list<array<string, mixed>>  $messages
 * @param  array<string, int>  $authFailuresByMessageId
 */
function bindFakeGmailClientForAccount(
    array $messages,
    string $accountEmail = 'test@example.com',
    array $authFailuresByMessageId = [],
): void {
    $fake = new FakeGmailApiClient($messages, $accountEmail, $authFailuresByMessageId);

    app()->bind(GmailClientFactory::class, static fn (): GmailClientFactory => new class($fake) extends GmailClientFactory
    {
        public function __construct(private readonly GmailApiClientInterface $client) {}

        public function make(string $credentialsPath, string $accountEmail): GmailApiClientInterface
        {
            return $this->client;
        }
    });

    app()->instance(FakeGmailApiClient::class, $fake);
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
