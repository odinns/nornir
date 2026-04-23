<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $reacted_at
 * @property array<string, mixed>|null $raw_reaction
 */
class LinkedinReaction extends Model
{
    protected $table = 'linkedin_reactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reacted_at' => 'immutable_datetime',
            'raw_reaction' => 'array',
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
