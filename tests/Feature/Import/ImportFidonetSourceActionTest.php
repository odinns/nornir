<?php

declare(strict_types=1);

use App\Actions\Import\ImportFidonetSourceAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\FidonetArea;
use App\Models\FidonetMessage;
use App\Models\FidonetMessageCleanup;
use App\Models\FidonetMessageObservation;
use App\Models\FidonetParticipant;
use App\Models\FidonetSource;
use App\Models\FidonetThread;
use App\Models\FidonetThreadMessage;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/fidonet'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('imports Odinn-thread-scoped fidonet rows from an external GoldED source', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-import-primary');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [
            'selection_mode' => 'odinn-thread-scope',
        ],
        importerOptions: [],
    ));

    $result = app(ImportFidonetSourceAction::class)($intake->dispatchPayload);

    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);
    expect(FidonetSource::query()->count())->toBe(1);
    expect(FidonetArea::query()->orderBy('area_code')->pluck('area_code')->all())->toBe(['EMAILTST', 'WINETDEV']);
    expect(FidonetMessage::query()->orderBy('canonical_message_id')->pluck('canonical_message_id')->all())->toBe([
        'msg-reply-1',
        'msg-root-1',
        'msg-test-1',
    ]);
    expect(FidonetThread::query()->count())->toBe(2);
    expect(FidonetParticipant::query()->count())->toBeGreaterThanOrEqual(4);
    expect(FidonetMessageCleanup::query()->where('canonical_message_id', 'msg-test-1')->value('is_test_like'))->toBeTrue();
});

it('reruns idempotently for the same fidonet source boundary', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-import-repeat');

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [
            'selection_mode' => 'odinn-thread-scope',
        ],
        importerOptions: [],
    ));

    $importer = app(ImportFidonetSourceAction::class);
    $firstResult = $importer($intake->dispatchPayload);
    $secondResult = $importer($intake->dispatchPayload);

    expect($secondResult->run->is($firstResult->run))->toBeTrue();
    expect(FidonetSource::query()->count())->toBe(1);
    expect(FidonetMessage::query()->count())->toBe(3);
    expect(FidonetThread::query()->count())->toBe(2);
    expect(FidonetThreadMessage::query()->count())->toBe(3);
    expect(FidonetMessageObservation::query()->count())->toBe(3);
});

it('falls back to reply-chain thread derivation when thread_key is missing', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-import-reply-chain', [
        'messages' => [[
            'area_code' => 'WINETDEV',
            'msgno' => 1,
            'external_id' => 'reply-root',
            'subject' => 'Root',
            'from_name' => 'Odinn Sorensen',
            'from_address' => 'odinn@goldware.dk',
            'to_name' => 'Bo Noergaard',
            'body_text' => 'Hej Bo.',
            'posted_at' => '1995-09-02 10:00:00',
        ], [
            'area_code' => 'WINETDEV',
            'msgno' => 2,
            'external_id' => 'reply-child',
            'subject' => 'Re: Root',
            'from_name' => 'Bo Noergaard',
            'to_name' => 'Odinn Sorensen',
            'body_text' => 'OS> Hej Bo.',
            'reply_to_msgno' => 1,
            'reply_to_external_id' => 'reply-root',
            'posted_at' => '1995-09-02 11:00:00',
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    app(ImportFidonetSourceAction::class)($intake->dispatchPayload);

    expect(FidonetThread::query()->where('source_method', 'reply_chain')->count())->toBe(1);
    expect(FidonetThreadMessage::query()->count())->toBe(2);
});

it('fails when an in-scope fidonet message is missing a stable external id', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-import-missing-external-id', [
        'messages' => [[
            'area_code' => 'WINETDEV',
            'msgno' => 1,
            'external_id' => null,
            'subject' => 'Root',
            'from_name' => 'Odinn Sorensen',
            'from_address' => 'odinn@goldware.dk',
            'to_name' => 'Bo Noergaard',
            'body_text' => 'Hej Bo.',
            'thread_key' => 'thread-alpha',
        ]],
    ]);

    $intake = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    expect(fn () => app(ImportFidonetSourceAction::class)($intake->dispatchPayload))
        ->toThrow(InvalidArgumentException::class, 'FidoNet message is missing stable external_id');
});

it('creates distinct source sets for different fidonet scopes', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-import-distinct-scopes');
    $recordIntake = app(RecordIntakeAction::class);
    $importer = app(ImportFidonetSourceAction::class);

    $emailIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [
            'area_include_codes' => ['WINETDEV'],
        ],
        importerOptions: [],
    ));

    $allIntake = $recordIntake(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [],
        importerOptions: [],
    ));

    $importer($emailIntake->dispatchPayload);
    $importer($allIntake->dispatchPayload);

    expect(FidonetSource::query()->count())->toBe(2);
    expect(FidonetMessageObservation::query()
        ->pluck('fidonet_source_id')
        ->unique()
        ->count())->toBe(2);
});
