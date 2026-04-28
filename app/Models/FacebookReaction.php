<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $facebook_archive_id
 * @property int|null $facebook_person_id
 * @property string $canonical_key
 * @property int|null $published_timestamp
 * @property CarbonImmutable|null $published_at
 * @property string $reaction
 * @property array<string, mixed>|null $raw_reaction
 * @property-read FacebookArchive $archive
 * @property-read FacebookPerson|null $person
 * @property-read Collection<int, FacebookReactionObservation> $observations
 */
class FacebookReaction extends Model
{
    protected $table = 'facebook_reactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_timestamp' => 'integer',
            'published_at' => 'immutable_datetime',
            'raw_reaction' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FacebookArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(FacebookArchive::class, 'facebook_archive_id');
    }

    /**
     * @return BelongsTo<FacebookPerson, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(FacebookPerson::class, 'facebook_person_id');
    }

    /**
     * @return HasMany<FacebookReactionObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(FacebookReactionObservation::class, 'facebook_reaction_id');
    }
}
