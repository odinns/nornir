<?php

declare(strict_types=1);

use App\Actions\Import\ImportGmailAction;
use App\Services\Gmail\GmailApiClientInterface;
use App\Services\Gmail\GmailClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('builds a gmail source-page handoff from the cli', function (): void {
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

    $intake = makeGmailIntake('odinn@example.com', 'from:me');
    $importResult = app(ImportGmailAction::class)($intake->dispatchPayload);

    $this->artisan('handoff:gmail-source-pages', [
        '--run-id' => $importResult->run->id,
    ])
        ->expectsOutputToContain('Building Gmail source-page handoff')
        ->expectsOutputToContain('Message count: 2')
        ->expectsOutputToContain('Handoff ready')
        ->assertSuccessful();
});
