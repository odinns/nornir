<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $recommended_at
 * @property array<string, mixed>|null $raw_recommendation
 */
class LinkedinRecommendation extends Model
{
    protected $table = 'linkedin_recommendations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recommended_at' => 'immutable_datetime',
            'raw_recommendation' => 'array',
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
