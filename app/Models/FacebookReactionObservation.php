<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facebook_reaction_id
 * @property int $facebook_archive_id
 * @property-read FacebookReaction $reaction
 * @property-read FacebookArchive $archive
 */
class FacebookReactionObservation extends Model
{
    protected $table = 'facebook_reaction_observations';

    protected $guarded = [];

    /**
     * @return BelongsTo<FacebookReaction, $this>
     */
    public function reaction(): BelongsTo
    {
        return $this->belongsTo(FacebookReaction::class, 'facebook_reaction_id');
    }

    /**
     * @return BelongsTo<FacebookArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(FacebookArchive::class, 'facebook_archive_id');
    }
}
