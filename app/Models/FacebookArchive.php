<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $source_key
 * @property string $source_locator
 * @property string $access_mode
 * @property-read FacebookProfileSnapshot|null $profileSnapshot
 * @property-read Collection<int, FacebookThread> $threads
 * @property-read Collection<int, FacebookPost> $posts
 * @property-read Collection<int, FacebookComment> $comments
 * @property-read Collection<int, FacebookReaction> $reactions
 */
class FacebookArchive extends Model
{
    protected $table = 'facebook_archives';

    protected $guarded = [];

    /**
     * @return HasOne<FacebookProfileSnapshot, $this>
     */
    public function profileSnapshot(): HasOne
    {
        return $this->hasOne(FacebookProfileSnapshot::class, 'facebook_archive_id');
    }

    /**
     * @return HasMany<FacebookThread, $this>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(FacebookThread::class, 'facebook_archive_id');
    }

    /**
     * @return HasMany<FacebookPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(FacebookPost::class, 'facebook_archive_id');
    }

    /**
     * @return HasMany<FacebookComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(FacebookComment::class, 'facebook_archive_id');
    }

    /**
     * @return HasMany<FacebookReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(FacebookReaction::class, 'facebook_archive_id');
    }
}
