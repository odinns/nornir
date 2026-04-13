<?php

declare(strict_types=1);

namespace App\Services\Gmail;

use Google\Client;
use Google\Service\Gmail;
use RuntimeException;

class GmailApiClient implements GmailApiClientInterface
{
    private Gmail $service;

    public function __construct(string $credentialsPath)
    {
        $client = new Client;
        $client->setAuthConfig($credentialsPath);
        $client->setScopes([Gmail::GMAIL_READONLY]);
        $client->setAccessType('offline');

        $tokenPath = dirname($credentialsPath).'/token.json';

        if (file_exists($tokenPath)) {
            $token = json_decode((string) file_get_contents($tokenPath), true);

            if (is_array($token)) {
                $client->setAccessToken($token);
            }
        }

        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken() !== null) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents($tokenPath, json_encode($client->getAccessToken(), JSON_THROW_ON_ERROR));
            } else {
                throw new RuntimeException(
                    "No valid token found. Run: php artisan gmail:auth {$credentialsPath}"
                );
            }
        }

        $this->service = new Gmail($client);
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
}
