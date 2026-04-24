<?php

declare(strict_types=1);

namespace App\Services\Gmail;

interface GmailApiClientInterface
{
    public function getAccountEmail(): string;

    public function refreshAuthentication(): void;

    /**
     * @return array{messages: list<array{id: string, threadId: string}>, nextPageToken: ?string}
     */
    public function listMessages(string $query, ?string $pageToken = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getMessage(string $messageId): array;
}
