<?php

declare(strict_types=1);

use App\Models\Run;
use App\Services\Gmail\GmailApiClientInterface;
use App\Services\Gmail\GmailClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/gmail'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

it('imports gmail messages from the cli with useful output', function (): void {
    $credentialsPath = createGmailCredentialsFixture('gmail-console');

    $fake = new FakeGmailApiClient([
        buildGmailMessage(['id' => 'msg-001', 'threadId' => 'thread-001']),
        buildGmailMessage(['id' => 'msg-002', 'threadId' => 'thread-001']),
    ]);

    app()->bind(GmailClientFactory::class, static fn (): GmailClientFactory => new class($fake) extends GmailClientFactory
    {
        public function __construct(private readonly GmailApiClientInterface $client) {}

        public function make(string $credentialsPath, string $accountEmail): GmailApiClientInterface
        {
            return $this->client;
        }
    });

    $this->artisan('import:gmail', [
        'source' => $credentialsPath,
        '--account' => 'odinn@example.com',
        '--query' => 'from:me',
    ])
        ->expectsOutputToContain('Recording intake for Gmail account')
        ->expectsOutputToContain('Importing Gmail messages')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(1);
    expect(DB::table('gmail_messages')->count())->toBe(2);
    expect(DB::table('runs')->where('status', Run::STATUS_SUCCEEDED)->count())->toBe(1);
});

it('fails when the account flag is missing', function (): void {
    $credentialsPath = createGmailCredentialsFixture('gmail-console-missing-account');

    $this->artisan('import:gmail', [
        'source' => $credentialsPath,
        '--query' => 'from:me',
    ])
        ->assertFailed();
});

it('fails when the query flag is missing', function (): void {
    $credentialsPath = createGmailCredentialsFixture('gmail-console-missing-query');

    $this->artisan('import:gmail', [
        'source' => $credentialsPath,
        '--account' => 'odinn@example.com',
    ])
        ->assertFailed();
});
