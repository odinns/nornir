<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $observed_at
 * @property array<string, mixed>|null $raw_media
 */
class LinkedinRichMedia extends Model
{
    protected $table = 'linkedin_rich_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'observed_at' => 'immutable_datetime',
            'raw_media' => 'array',
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
