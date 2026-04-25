<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $twitter_archive_id
 * @property string $account_id
 * @property string|null $username
 * @property string|null $display_name
 * @property string|null $created_at_source
 * @property CarbonImmutable|null $account_created_at
 * @property array<string, mixed>|null $raw_account
 */
class TwitterAccount extends Model
{
    protected $table = 'twitter_accounts';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'account_created_at' => 'immutable_datetime',
            'raw_account' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TwitterArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(TwitterArchive::class, 'twitter_archive_id');
    }
}
