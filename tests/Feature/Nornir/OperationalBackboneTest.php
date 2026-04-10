<?php

declare(strict_types=1);

use App\Data\Shared\RecordArtifactData;
use App\Data\Shared\StartRunData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ArtifactRecorder;
use App\Services\Nornir\ProvenanceWriter;
use App\Services\Nornir\RunRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the shared operational tables', function (): void {
    expect(Schema::hasTable('intake_records'))->toBeTrue();
    expect(Schema::hasTable('runs'))->toBeTrue();
    expect(Schema::hasTable('run_events'))->toBeTrue();
    expect(Schema::hasTable('run_artifacts'))->toBeTrue();
    expect(Schema::hasTable('provenance_links'))->toBeTrue();
});

it('reuses the same logical run for the same subsystem operation and idempotency key', function (): void {
    $recorder = app(RunRecorder::class);

    $firstRun = $recorder->start(new StartRunData(
        subsystem: 'import',
        operation: 'chatgpt-import',
        inputScope: ['archive' => 'chatgpt-export-2026-04-10'],
        idempotencyKey: 'chatgpt-import:chatgpt-export-2026-04-10',
    ));

    $secondRun = $recorder->start(new StartRunData(
        subsystem: 'import',
        operation: 'chatgpt-import',
        inputScope: ['archive' => 'chatgpt-export-2026-04-10'],
        idempotencyKey: 'chatgpt-import:chatgpt-export-2026-04-10',
    ));

    expect($secondRun->is($firstRun))->toBeTrue();
    expect(Run::query()->count())->toBe(1);
    expect($secondRun->status)->toBe('running');
});

it('records lifecycle events when a run starts and succeeds', function (): void {
    $recorder = app(RunRecorder::class);

    $run = $recorder->start(new StartRunData(
        subsystem: 'import',
        operation: 'chatgpt-import',
        inputScope: ['archive' => 'chatgpt-export-2026-04-10'],
        idempotencyKey: 'chatgpt-import:chatgpt-export-2026-04-10',
    ));

    $completedRun = $recorder->complete($run);

    expect($completedRun->status)->toBe('succeeded');
    expect($completedRun->finished_at)->not->toBeNull();
    expect($completedRun->events()->orderBy('id')->pluck('event')->all())->toBe([
        'run_started',
        'run_succeeded',
    ]);
});

it('records failure and partial completion states without overwriting event history', function (): void {
    $recorder = app(RunRecorder::class);

    $run = $recorder->start(new StartRunData(
        subsystem: 'muninn',
        operation: 'timeline-rebuild',
        inputScope: ['person' => 'odinn'],
        idempotencyKey: 'timeline-rebuild:odinn',
    ));

    $failedRun = $recorder->fail($run, 'evidence bundle missing');

    expect($failedRun->status)->toBe('failed');
    expect($failedRun->failure_summary)->toBe('evidence bundle missing');
    expect($failedRun->events()->orderBy('id')->pluck('event')->all())->toBe([
        'run_started',
        'run_failed',
    ]);

    $partialRun = $recorder->markPartial($failedRun, 'timeline rebuilt with gaps');

    expect($partialRun->status)->toBe('partially_completed');
    expect($partialRun->failure_summary)->toBe('timeline rebuilt with gaps');
    expect($partialRun->events()->orderBy('id')->pluck('event')->all())->toBe([
        'run_started',
        'run_failed',
        'run_partially_completed',
    ]);
});

it('bounds failure summaries so a giant exception does not break run recording', function (): void {
    $recorder = app(RunRecorder::class);

    $run = $recorder->start(new StartRunData(
        subsystem: 'import',
        operation: 'chatgpt-import',
        inputScope: ['archive' => 'chatgpt-export-2026-04-10'],
        idempotencyKey: 'chatgpt-import:oversized-failure',
    ));

    $failedRun = $recorder->fail($run, str_repeat('db blew up ', 300));

    expect($failedRun->status)->toBe(Run::STATUS_FAILED);
    expect($failedRun->failure_summary)->toHaveLength(1_000);
    expect($failedRun->failure_summary)->toEndWith('...');
    expect($failedRun->events()->latest('id')->value('payload'))->toMatchArray([
        'status' => Run::STATUS_FAILED,
        'failure_summary' => $failedRun->failure_summary,
    ]);
});

it('records artifacts as discoverable children of a run', function (): void {
    $recorder = app(RunRecorder::class);
    $artifactRecorder = app(ArtifactRecorder::class);

    $run = $recorder->start(new StartRunData(
        subsystem: 'import',
        operation: 'chatgpt-import',
        inputScope: ['archive' => 'chatgpt-export-2026-04-10'],
        idempotencyKey: 'chatgpt-import:chatgpt-export-2026-04-10',
    ));

    $artifact = $artifactRecorder->record(new RecordArtifactData(
        runId: $run->id,
        artifactKind: 'run-summary',
        locator: 'data/runs/import/chatgpt-import-2026-04-10.json',
        classification: 'diagnostic',
        metadata: ['source' => 'chatgpt'],
    ));

    expect($artifact->run->is($run))->toBeTrue();
    expect($run->artifacts()->orderBy('id')->pluck('locator')->all())->toBe([
        'data/runs/import/chatgpt-import-2026-04-10.json',
    ]);
});

it('records provenance links from an output claim back to supporting evidence', function (): void {
    $recorder = app(RunRecorder::class);
    $provenanceWriter = app(ProvenanceWriter::class);

    $run = $recorder->start(new StartRunData(
        subsystem: 'muninn',
        operation: 'biography-pass',
        inputScope: ['person' => 'odinn'],
        idempotencyKey: 'biography-pass:odinn',
    ));

    $firstLink = $provenanceWriter->link(new WriteProvenanceLinkData(
        runId: $run->id,
        outputTarget: 'wiki/muninn/biography.md',
        claimKey: 'section:intro',
        evidenceType: 'source-row',
        evidenceRef: 'chatgpt_messages:42',
    ));

    $secondLink = $provenanceWriter->link(new WriteProvenanceLinkData(
        runId: $run->id,
        outputTarget: 'wiki/muninn/biography.md',
        claimKey: 'section:intro',
        evidenceType: 'evidence-bundle',
        evidenceRef: 'bundle:chatgpt-import-2026-04-10',
    ));

    expect($firstLink->run->is($run))->toBeTrue();
    expect($secondLink->run->is($run))->toBeTrue();
    expect($run->provenanceLinks()->orderBy('id')->pluck('evidence_ref')->all())->toBe([
        'chatgpt_messages:42',
        'bundle:chatgpt-import-2026-04-10',
    ]);
});
