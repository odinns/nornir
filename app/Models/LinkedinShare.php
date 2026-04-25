<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property CarbonImmutable|null $shared_at
 * @property array<string, mixed>|null $raw_share
 */
class LinkedinShare extends Model
{
    protected $table = 'linkedin_shares';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'shared_at' => 'immutable_datetime',
            'raw_share' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LinkedinArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(LinkedinArchive::class, 'first_seen_linkedin_archive_id');
    }
}
