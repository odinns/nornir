<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property CarbonImmutable|null $endorsed_at
 * @property array<string, mixed>|null $raw_endorsement
 */
class LinkedinEndorsement extends Model
{
    protected $table = 'linkedin_endorsements';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'endorsed_at' => 'immutable_datetime',
            'raw_endorsement' => 'array',
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
    public function counterpart(): BelongsTo
    {
        return $this->belongsTo(LinkedinPerson::class, 'counterpart_linkedin_person_id');
    }
}
