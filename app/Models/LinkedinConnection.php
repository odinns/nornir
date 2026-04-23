<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $connected_at
 * @property array<string, mixed>|null $raw_connection
 */
class LinkedinConnection extends Model
{
    protected $table = 'linkedin_connections';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'connected_at' => 'immutable_datetime',
            'raw_connection' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LinkedinArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(LinkedinArchive::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return BelongsTo<LinkedinPerson, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(LinkedinPerson::class, 'linkedin_person_id');
    }
}
