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
 * @property string|null $account_id
 * @property string $screen_name
 * @property string|null $changed_at_source
 * @property CarbonImmutable|null $changed_at
 * @property array<string, mixed>|null $raw_change
 */
class TwitterScreenNameChange extends Model
{
    protected $table = 'twitter_screen_name_changes';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'changed_at' => 'immutable_datetime',
            'raw_change' => 'array',
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
