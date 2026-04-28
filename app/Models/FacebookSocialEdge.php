<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facebook_archive_id
 * @property int $facebook_person_id
 * @property string $edge_type
 * @property CarbonImmutable|null $observed_at
 * @property-read FacebookArchive $archive
 * @property-read FacebookPerson $person
 */
class FacebookSocialEdge extends Model
{
    protected $table = 'facebook_social_edges';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'observed_at' => 'immutable_datetime',
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
}
