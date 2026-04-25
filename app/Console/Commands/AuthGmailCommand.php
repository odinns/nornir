<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Google\Client;
use Google\Service\Gmail;
use Illuminate\Console\Command;

class AuthGmailCommand extends Command
{
    protected $signature = 'gmail:auth
        {credentials : Path to your OAuth2 credentials.json from Google Cloud Console}';

    protected $description = 'Authorise Gmail API access and save a token for use by import:gmail.';

    public function handle(): int
    {
        $credentialsPath = $this->argument('credentials');

        if (! is_string($credentialsPath) || $credentialsPath === '') {
            $this->error('No credentials path provided.');

            return self::FAILURE;
        }

        if (! file_exists($credentialsPath)) {
            $this->error("Credentials file not found: {$credentialsPath}");

            return self::FAILURE;
        }

        $client = new Client;
        $client->setAuthConfig($credentialsPath);
        $client->setScopes([Gmail::GMAIL_READONLY]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $tokenPath = dirname($credentialsPath).'/token.json';

        if (file_exists($tokenPath)) {
            $existing = json_decode((string) file_get_contents($tokenPath), true);

            if (is_array($existing)) {
                $client->setAccessToken($existing);

                if (! $client->isAccessTokenExpired()) {
                    $this->info('Token already valid — no re-auth needed.');
                    $this->line("Token path: {$tokenPath}");

                    return self::SUCCESS;
                }

                if ($client->getRefreshToken() !== null) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    file_put_contents($tokenPath, json_encode($client->getAccessToken(), JSON_THROW_ON_ERROR));
                    $this->info('Token refreshed.');
                    $this->line("Token path: {$tokenPath}");

                    return self::SUCCESS;
                }
            }
        }

        $authUrl = $client->createAuthUrl();

        $this->info('Open this URL in your browser to authorise access:');
        $this->line('');
        $this->line($authUrl);
        $this->line('');
        $this->line('After approving access, Google will redirect to localhost — the browser will show');
        $this->line('"connection refused". That\'s fine. Look at the URL bar:');
        $this->line('');
        $this->line('  http://localhost/?code=<THE_CODE_IS_HERE>&scope=...');
        $this->line('');
        $this->line('Copy the value after "code=" and before "&scope" and paste it below.');
        $this->line('');

        $code = $this->ask('Authorisation code');

        if (! is_string($code) || $code === '') {
            $this->error('No code provided.');

            return self::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $this->error('Token exchange failed: '.($token['error_description'] ?? $token['error']));

            return self::FAILURE;
        }

        $client->setAccessToken($token);
        file_put_contents($tokenPath, json_encode($client->getAccessToken(), JSON_THROW_ON_ERROR));

        $this->info('Authorisation complete. Token saved.');
        $this->line("Token path: {$tokenPath}");
        $this->line('');
        $this->line('You can now run:');
        $this->line("  php artisan import:gmail {$credentialsPath} --query=\"from:me\"");

        return self::SUCCESS;
    }
}
