<?php

declare(strict_types=1);

use App\Actions\Import\BuildFidonetSourcePageHandoffAction;
use App\Actions\Import\ImportFidonetSourceAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('builds a compile-facing handoff from imported fidonet rows', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-handoff');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [
            'selection_mode' => 'odinn-thread-scope',
        ],
        importerOptions: [],
    ));

    $importResult = app(ImportFidonetSourceAction::class)($intake->dispatchPayload);
    $handoff = app(BuildFidonetSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->sourceType)->toBe('fidonet');
    expect($handoff->handoffType)->toBe('source-pages');
    expect($handoff->canonicalScope)->toMatchArray([
        'source_locator' => $fixture['env_path'],
        'row_counts' => [
            'source_sets' => 1,
            'areas' => 2,
            'threads' => 2,
            'messages' => 3,
            'participants' => 4,
        ],
    ]);
    expect($handoff->canonicalScope['handoff_scope']['selection_mode'])->toBe('odinn-thread-scope');
});

it('rejects runs that are not successful fidonet imports', function (): void {
    $run = Run::query()->create([
        'subsystem' => 'muninn',
        'operation' => 'timeline-pass',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => ['person' => 'odinn'],
        'idempotency_key' => 'timeline-pass:odinn',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(fn () => app(BuildFidonetSourcePageHandoffAction::class)($run->id))
        ->toThrow(InvalidArgumentException::class, 'Run does not describe a successful FidoNet import.');
});

it('builds the fidonet handoff without rescanning the external source env file contents', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-handoff-no-raw');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    $importResult = app(ImportFidonetSourceAction::class)($intake->dispatchPayload);
    File::delete($fixture['env_path']);

    $handoff = app(BuildFidonetSourcePageHandoffAction::class)($importResult->run->id);

    expect($handoff->canonicalScope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'areas' => 2,
        'threads' => 2,
        'messages' => 3,
    ]);
});

it('builds fidonet handoffs from run-specific observations when later scopes overlap', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-handoff-scoped');
    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportFidonetSourceAction::class);

    $firstIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [
            'area_include_codes' => ['WINETDEV'],
        ],
        importerOptions: [],
    ));

    $secondIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    $firstRun = $importer($firstIntake->dispatchPayload)->run;
    $importer($secondIntake->dispatchPayload);

    $handoff = app(BuildFidonetSourcePageHandoffAction::class)($firstRun->id);

    expect($handoff->canonicalScope['row_counts'])->toMatchArray([
        'source_sets' => 1,
        'areas' => 1,
        'threads' => 1,
        'messages' => 2,
    ]);
});
