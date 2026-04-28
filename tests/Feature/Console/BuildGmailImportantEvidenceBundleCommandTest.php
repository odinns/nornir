<?php

declare(strict_types=1);

use App\Actions\Import\ImportGmailAction;
use App\Models\RunArtifact;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/gmail'));
    File::deleteDirectory(base_path('data/reviews'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
    date_default_timezone_set('Europe/Copenhagen');
    $this->travelTo(CarbonImmutable::parse('2026-04-20 15:45:00', 'Europe/Copenhagen'));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('data/reviews'));
    File::deleteDirectory(base_path('data/test-fixtures/gmail'));
});

it('builds a gmail important evidence bundle from the cli as json', function (): void {
    bindFakeGmailClientForAccount([
        buildGmailMessage([
            'id' => 'msg-001',
            'threadId' => 'thread-001',
            'labelIds' => ['INBOX', 'UNREAD'],
            'internalDate' => '1776677400000',
            'snippet' => 'Can you respond today?',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Sender <sender@example.com>'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Quick question'],
                ],
                'body' => ['data' => base64_encode('Can you respond today?'), 'size' => 22],
                'parts' => [],
            ],
        ]),
        buildGmailMessage([
            'id' => 'msg-002',
            'threadId' => 'thread-002',
            'labelIds' => ['INBOX'],
            'internalDate' => '1776677400000',
            'snippet' => 'Can you respond today?',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Other <other@example.com>'],
                    ['name' => 'To', 'value' => 'odinn@example.com'],
                    ['name' => 'Subject', 'value' => 'Another question'],
                ],
                'body' => ['data' => base64_encode('Can you respond today?'), 'size' => 22],
                'parts' => [],
            ],
        ]),
    ], 'odinn@example.com');

    $importResult = app(ImportGmailAction::class)(makeGmailIntake('label:important')->dispatchPayload);

    Artisan::call('evidence:gmail-important', [
        '--run-id' => $importResult->run->id,
        '--limit' => '1',
        '--json' => true,
    ]);

    $decoded = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toMatchArray([
        'source_run_id' => $importResult->run->id,
        'matched_count' => 1,
        'source_set_ids' => [$importResult->summary['source_set_id']],
    ]);
    expect($decoded['bundle_path'])->toBeString();
    expect($decoded['evidence_run_id'])->toBeInt();
    expect(File::exists($decoded['bundle_path']))->toBeTrue();

    $bundle = json_decode((string) File::get($decoded['bundle_path']), true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($bundle)) {
        throw new RuntimeException('Evidence bundle JSON did not decode to an object.');
    }

    expect($bundle)->toMatchArray([
        'schema_version' => 1,
        'bundle_type' => 'gmail-important-mail',
        'source_type' => 'gmail',
        'source_run_id' => $importResult->run->id,
    ]);
    expect(RunArtifact::query()
        ->where('run_id', $decoded['evidence_run_id'])
        ->where('artifact_kind', 'gmail-important-evidence-bundle')
        ->where('locator', $decoded['bundle_path'])
        ->exists())->toBeTrue();
});

it('fails clearly when the requested gmail import run is invalid', function (): void {
    artisanCommand($this, 'evidence:gmail-important', [
        '--run-id' => '999999',
        '--json' => true,
    ])
        ->expectsOutputToContain('Run does not describe a successful Gmail import.')
        ->assertFailed();
});
