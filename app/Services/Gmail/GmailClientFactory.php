<?php

declare(strict_types=1);

namespace App\Services\Gmail;

class GmailClientFactory
{
    public function make(string $credentialsPath, string $accountEmail): GmailApiClientInterface
    {
        return new GmailApiClient($credentialsPath, $accountEmail);
    }
}
