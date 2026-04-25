<?php

declare(strict_types=1);

use App\Services\Gmail\GmailApiClientInterface;
use App\Services\Gmail\GmailClientFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

it('prints triage results as json through the command', function (): void {
    config()->set('gmail_triage.credentials_path', '/tmp/fake-credentials.json');

    $fake = new FakeGmailApiClient([
        buildGmailMessage([
            'id' => 'msg-001',
            'threadId' => 'thread-001',
            'labelIds' => ['INBOX', 'UNREAD'],
            'internalDate' => '1776691800000',
            'snippet' => 'Can you respond today?',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'sender@example.com'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Quick question'],
                ],
                'body' => ['data' => base64_encode('Can you respond today?'), 'size' => 22],
                'parts' => [],
            ],
        ]),
    ], 'odinn@example.com');

    app()->bind(GmailClientFactory::class, static fn (): GmailClientFactory => new class($fake) extends GmailClientFactory
    {
        public function __construct(private readonly GmailApiClientInterface $client) {}

        public function make(string $credentialsPath, string $accountEmail): GmailApiClientInterface
        {
            return $this->client;
        }
    });

    Artisan::call('gmail:triage-important', [
        '--window' => 'last 7 days',
        '--json' => true,
    ]);

    $output = Artisan::output();
    $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($decoded['matched_count'])->toBe(1);
    expect($decoded['items'][0]['message_id'])->toBe('msg-001');
    expect($decoded['items'][0]['urgency'])->toBe('today');
    expect(DB::table('gmail_messages')->count())->toBe(0);
});

it('fails clearly when the Gmail credentials path is missing', function (): void {
    config()->set('gmail_triage.credentials_path');

    artisanCommand($this, 'gmail:triage-important', [
        '--window' => 'last 7 days',
        '--json' => true,
    ])
        ->expectsOutputToContain('Set NORNIR_GMAIL_CREDENTIALS')
        ->assertFailed();
});

it('fails clearly when the limit is invalid', function (): void {
    config()->set('gmail_triage.credentials_path', '/tmp/fake-credentials.json');

    artisanCommand($this, 'gmail:triage-important', [
        '--window' => 'last 7 days',
        '--limit' => '0',
        '--json' => true,
    ])
        ->expectsOutputToContain('The --limit option must be a positive integer.')
        ->assertFailed();
});
