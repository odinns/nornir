<?php

declare(strict_types=1);

use App\Actions\Import\Support\SourceObservationStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('preserves created_at and refreshes updated_at when upserting an existing row', function (): void {
    $store = app(SourceObservationStore::class);

    $this->travelTo(now()->startOfSecond());

    $id = $store->upsertAndReturnId(
        table: 'gmail_accounts',
        unique: ['account_key' => sha1('first@example.com')],
        values: ['account_email' => 'first@example.com', 'access_mode' => 'api'],
    );

    $createdAt = DB::table('gmail_accounts')->where('id', $id)->value('created_at');
    $firstUpdatedAt = DB::table('gmail_accounts')->where('id', $id)->value('updated_at');

    $this->travel(5)->seconds();

    $sameId = $store->upsertAndReturnId(
        table: 'gmail_accounts',
        unique: ['account_key' => sha1('first@example.com')],
        values: ['account_email' => 'first@example.com', 'display_name' => 'First User', 'access_mode' => 'api'],
    );

    $row = DB::table('gmail_accounts')->where('id', $sameId)->first();
    if ($row === null) {
        throw new RuntimeException('Expected the upserted Gmail account row to exist.');
    }

    expect($sameId)->toBe($id);
    expect($row->created_at)->toBe($createdAt);
    expect($row->updated_at)->not->toBe($firstUpdatedAt);
    expect($row->display_name)->toBe('First User');
});
