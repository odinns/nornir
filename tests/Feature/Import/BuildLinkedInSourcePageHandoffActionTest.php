<?php

declare(strict_types=1);

use App\Actions\Import\BuildLinkedInSourcePageHandoffAction;
use App\Actions\Import\ImportLinkedInArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('builds a compile-facing handoff from canonical linkedin rows', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-handoff');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportLinkedInArchiveAction::class)($intake->dispatchPayload);
    $handoff = app(BuildLinkedInSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->sourceType)->toBe('linkedin');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->owningRunId)->toBe($importResult->run->id);
    expect($handoff->canonicalScope)->toMatchArray([
        'source_locator' => $fixture['archive_path'],
        'accepted_root_paths' => [$fixture['archive_path']],
        'row_counts' => [
            'source_sets' => 1,
            'profile_snapshots' => 1,
            'positions' => 1,
            'endorsements' => 2,
            'conversations' => 1,
            'messages' => 2,
        ],
    ]);
});

it('rejects runs that are not successful linkedin imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'muninn',
        'operation' => 'timeline-pass',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['person' => 'odinn'],
        'idempotency_key' => 'timeline-pass:odinn',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildLinkedInSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful LinkedIn import.');
});

it('builds the linkedin handoff from canonical rows without rescanning the raw source path', function (): void {
    $fixture = createLinkedInFixtureArchive('linkedin-handoff-no-raw');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'linkedin',
        accessMode: 'local-path',
        sourceLocator: $fixture['archive_path'],
        scopeSnapshot: [
            'accepted_root_paths' => [$fixture['archive_path']],
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportLinkedInArchiveAction::class)($intake->dispatchPayload);

    File::deleteDirectory($fixture['root_path']);

    $handoff = app(BuildLinkedInSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->canonicalScope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'profile_snapshots' => 1,
        'positions' => 1,
        'endorsements' => 2,
        'conversations' => 1,
        'messages' => 2,
    ]);
});
