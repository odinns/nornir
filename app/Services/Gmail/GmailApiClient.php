<?php

declare(strict_types=1);

namespace App\Services\Gmail;

use Google\Client;
use Google\Service\Gmail;
use RuntimeException;

class GmailApiClient implements GmailApiClientInterface
{
    private Client $client;

    private Gmail $service;

    private string $tokenPath;

    public function __construct(string $credentialsPath)
    {
        $this->tokenPath = dirname($credentialsPath).'/token.json';
        $this->client = new Client;
        $this->client->setAuthConfig($credentialsPath);
        $this->client->setScopes([Gmail::GMAIL_READONLY]);
        $this->client->setAccessType('offline');

        if (file_exists($this->tokenPath)) {
            $token = json_decode((string) file_get_contents($this->tokenPath), true);

            if (is_array($token)) {
                $this->client->setAccessToken($token);
            }
        }

        if ($this->client->isAccessTokenExpired()) {
            $this->refreshAuthentication();
        } else {
            $this->service = new Gmail($this->client);
        }
    }

    public function getAccountEmail(): string
    {
        $profile = $this->service->users->getProfile('me');

        return (string) $profile->getEmailAddress();
    }

    /**
     * {@inheritDoc}
     */
    public function listMessages(string $query, ?string $pageToken = null): array
    {
        $params = ['q' => $query, 'maxResults' => 500];

        if ($pageToken !== null) {
            $params['pageToken'] = $pageToken;
        }

        $response = $this->service->users_messages->listUsersMessages('me', $params);

        $messages = $response->getMessages();

        if ($messages === null) {
            return ['messages' => [], 'nextPageToken' => null];
        }

        /** @var list<array{id: string, threadId: string}> $stubs */
        $stubs = array_values(array_map(
            static fn ($m): array => ['id' => (string) $m->getId(), 'threadId' => (string) $m->getThreadId()],
            $messages,
        ));

        return [
            'messages' => $stubs,
            'nextPageToken' => $response->getNextPageToken() ?? null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(string $messageId): array
    {
        $message = $this->service->users_messages->get('me', $messageId, ['format' => 'full']);

        $result = $message->toSimpleObject();

        if (! is_object($result)) {
            throw new RuntimeException("Could not retrieve message: {$messageId}");
        }

        return (array) json_decode((string) json_encode($result), true);
    }

    public function refreshAuthentication(): void
    {
        $refreshToken = $this->client->getRefreshToken();

        if ($refreshToken === null) {
            throw new RuntimeException('No valid Gmail refresh token found. Run: php artisan gmail:auth <credentials-path>');
        }

        $refreshedToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (($refreshedToken['error'] ?? null) !== null) {
            $description = (string) ($refreshedToken['error_description'] ?? $refreshedToken['error']);

            throw new RuntimeException('Could not refresh Gmail access token: '.$description);
        }

        $currentToken = $this->client->getAccessToken();

        if (! is_array($currentToken)) {
            throw new RuntimeException('Could not read refreshed Gmail access token.');
        }

        if (! array_key_exists('refresh_token', $currentToken)) {
            $currentToken['refresh_token'] = $refreshToken;
            $this->client->setAccessToken($currentToken);
        }

        file_put_contents($this->tokenPath, json_encode($currentToken, JSON_THROW_ON_ERROR));

        $this->service = new Gmail($this->client);
    }
}
