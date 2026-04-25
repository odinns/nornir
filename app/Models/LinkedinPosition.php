<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property CarbonImmutable|null $started_on
 * @property CarbonImmutable|null $finished_on
 * @property array<string, mixed>|null $raw_position
 */
class LinkedinPosition extends Model
{
    protected $table = 'linkedin_positions';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'started_on' => 'immutable_datetime',
            'finished_on' => 'immutable_datetime',
            'raw_position' => 'array',
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
