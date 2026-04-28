<?php

declare(strict_types=1);

use App\Actions\Import\Support\SourceObservationStore;
use App\Models\GmailAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preserves created_at and refreshes updated_at when upserting an existing row', function (): void {
    $store = app(SourceObservationStore::class);

    $this->travelTo(now()->startOfSecond());

    $id = $store->upsertAndReturnId(
        table: 'gmail_accounts',
        unique: ['account_key' => sha1('first@example.com')],
        values: ['account_email' => 'first@example.com', 'access_mode' => 'api'],
    );

    $account = GmailAccount::query()->findOrFail($id);
    $createdAt = $account->created_at?->toDateTimeString();
    $firstUpdatedAt = $account->updated_at?->toDateTimeString();

    $this->travel(5)->seconds();

    $sameId = $store->upsertAndReturnId(
        table: 'gmail_accounts',
        unique: ['account_key' => sha1('first@example.com')],
        values: ['account_email' => 'first@example.com', 'display_name' => 'First User', 'access_mode' => 'api'],
    );

    $row = GmailAccount::query()->findOrFail($sameId);

    expect($sameId)->toBe($id);
    expect($row->created_at?->toDateTimeString())->toBe($createdAt);
    expect($row->updated_at?->toDateTimeString())->not->toBe($firstUpdatedAt);
    expect($row->display_name)->toBe('First User');
});
