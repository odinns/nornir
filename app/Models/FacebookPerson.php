<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $display_name
 * @property-read Collection<int, FacebookProfileSnapshot> $profileSnapshots
 * @property-read Collection<int, FacebookSocialEdge> $socialEdges
 */
class FacebookPerson extends Model
{
    protected $table = 'facebook_people';

    protected $guarded = [];

    /**
     * @return HasMany<FacebookProfileSnapshot, $this>
     */
    public function profileSnapshots(): HasMany
    {
        return $this->hasMany(FacebookProfileSnapshot::class, 'facebook_person_id');
    }

    /**
     * @return HasMany<FacebookSocialEdge, $this>
     */
    public function socialEdges(): HasMany
    {
        return $this->hasMany(FacebookSocialEdge::class, 'facebook_person_id');
    }
}
