<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $instagram_account_id
 * @property string $snapshot_key
 * @property string|null $username
 * @property string|null $display_name
 * @property string|null $email
 * @property string|null $phone_number
 * @property CarbonImmutable|null $snapshotted_at
 * @property array<string, mixed>|null $raw_payload
 * @property-read InstagramAccount $account
 */
class InstagramProfileSnapshot extends Model
{
    protected $table = 'instagram_profile_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshotted_at' => 'immutable_datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<InstagramAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }
}
