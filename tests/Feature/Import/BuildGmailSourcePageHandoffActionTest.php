<?php

declare(strict_types=1);

use App\Actions\Import\BuildGmailSourcePageHandoffAction;
use App\Actions\Import\ImportGmailAction;
use App\Models\Run;
use App\Services\Gmail\GmailApiClientInterface;
use App\Services\Gmail\GmailClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/gmail'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

it('builds a compile-facing handoff from canonical gmail rows', function (): void {
    $credentialsPath = createGmailCredentialsFixture('gmail-handoff');

    $fake = new FakeGmailApiClient([
        buildGmailMessage(['id' => 'msg-001', 'threadId' => 'thread-001']),
        buildGmailMessage(['id' => 'msg-002', 'threadId' => 'thread-002']),
    ]);

    app()->bind(GmailClientFactory::class, static fn (): GmailClientFactory => new class($fake) extends GmailClientFactory
    {
        public function __construct(private readonly GmailApiClientInterface $client) {}

        public function make(string $credentialsPath, string $accountEmail): GmailApiClientInterface
        {
            return $this->client;
        }
    });

    $intake = makeGmailIntake('from:me');
    $importResult = app(ImportGmailAction::class)($intake->dispatchPayload);
    $handoff = app(BuildGmailSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->sourceType)->toBe('gmail');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($handoff->canonicalScope['account_email'])->toBe('test@example.com');
    expect($handoff->canonicalScope['row_counts']['messages'])->toBe(2);
    expect($handoff->canonicalScope['row_counts']['threads'])->toBe(2);
});

it('rejects runs that are not successful gmail imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'muninn',
        'operation' => 'timeline-pass',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['person' => 'odinn'],
        'idempotency_key' => 'timeline-pass:odinn',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildGmailSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful Gmail import.');
});
