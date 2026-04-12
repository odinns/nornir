<?php

declare(strict_types=1);

use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/intake'));
});

it('records a fidonet database intake from a GoldED env file boundary', function (): void {
    $fixture = createFidonetFixtureSource('fidonet-intake');

    $result = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'fidonet',
        accessMode: 'database',
        sourceLocator: $fixture['env_path'],
        scopeSnapshot: [
            'area_include_codes' => ['WINETDEV'],
            'selection_mode' => 'odinn-thread-scope',
        ],
        importerOptions: [],
    ));

    expect($result->dispatchPayload->sourceType)->toBe('fidonet');
    expect($result->dispatchPayload->accessMode)->toBe('database');
    expect($result->dispatchPayload->sourceLocator)->toBe($fixture['env_path']);
    expect($result->dispatchPayload->importerKey)->toBe('fidonet');
    expect($result->dispatchPayload->scopeSnapshot)->toBe([
        'area_include_codes' => ['WINETDEV'],
        'selection_mode' => 'odinn-thread-scope',
    ]);
});
