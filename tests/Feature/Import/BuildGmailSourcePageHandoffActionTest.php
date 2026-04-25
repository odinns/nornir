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
    /** @var array{account_email:string, query:string, tables:list<string>, source_set_ids:list<int>, handoff_scope:array{source_set_ids:list<int>, selection_mode:string}, row_counts:array{source_sets:int, threads:int, messages:int}} $scope */
    $scope = $handoff->canonicalScope;
    $sourceSetIds = $scope['source_set_ids'];

    expect($handoff->sourceType)->toBe('gmail');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($sourceSetIds)->toHaveCount(1);
    expect($scope)->toMatchArray([
        'account_email' => 'test@example.com',
        'query' => 'from:me',
        'tables' => [
            'gmail_source_sets',
            'gmail_accounts',
            'gmail_threads',
            'gmail_messages',
            'gmail_message_labels',
            'gmail_attachments',
            'gmail_message_observations',
        ],
        'source_set_ids' => $sourceSetIds,
        'handoff_scope' => [
            'source_set_ids' => $sourceSetIds,
            'selection_mode' => 'canonical-broad',
        ],
        'row_counts' => [
            'source_sets' => 1,
            'messages' => 2,
            'threads' => 2,
        ],
    ]);
});

it('scopes gmail handoff to the requested run boundary for the same account', function (): void {
    bindFakeGmailClientForAccount([
        buildGmailMessage(['id' => 'alpha-msg-001', 'threadId' => 'shared-thread-001']),
        buildGmailMessage(['id' => 'alpha-msg-002', 'threadId' => 'shared-thread-002']),
    ], 'shared@example.com');

    app(ImportGmailAction::class)(makeGmailIntake('label:alpha')->dispatchPayload);

    bindFakeGmailClientForAccount([
        buildGmailMessage(['id' => 'beta-msg-001', 'threadId' => 'shared-thread-003']),
    ], 'shared@example.com');

    $betaResult = app(ImportGmailAction::class)(makeGmailIntake('label:beta')->dispatchPayload);
    $handoff = app(BuildGmailSourcePageHandoffAction::class)($betaResult->run->id);
    /** @var array{account_email:string, query:string, source_set_ids:list<int>, row_counts:array{source_sets:int, threads:int, messages:int}} $scope */
    $scope = $handoff->canonicalScope;

    expect($scope['account_email'])->toBe('shared@example.com');
    expect($scope['query'])->toBe('label:beta');
    expect($scope['source_set_ids'])->toHaveCount(1);
    expect($scope['row_counts'])->toBe([
        'source_sets' => 1,
        'threads' => 1,
        'messages' => 1,
    ]);
});

it('rejects gmail runs that do not produce canonical rows for the bounded source set', function (): void {
    bindFakeGmailClientForAccount([], 'empty@example.com');

    $result = app(ImportGmailAction::class)(makeGmailIntake('label:empty')->dispatchPayload);

    expect(fn () => app(BuildGmailSourcePageHandoffAction::class)($result->run->id))
        ->toThrow(InvalidArgumentException::class, 'No canonical Gmail rows were found for the requested run.');
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
