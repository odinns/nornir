<?php

declare(strict_types=1);

namespace App\Services\Gmail;

use Google\Client;
use Google\Service\Gmail;
use RuntimeException;

class GmailApiClient implements GmailApiClientInterface
{
    private Gmail $service;

    public function __construct(string $credentialsPath, string $accountEmail)
    {
        $client = new Client;
        $client->setAuthConfig($credentialsPath);
        $client->setScopes([Gmail::GMAIL_READONLY]);
        $client->setSubject($accountEmail);
        $this->service = new Gmail($client);
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
