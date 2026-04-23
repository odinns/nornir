<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $account_key
 * @property string $username
 * @property string|null $display_name
 * @property string|null $email
 * @property string|null $phone_number
 * @property string $access_mode
 * @property-read Collection<int, InstagramProfileSnapshot> $profileSnapshots
 * @property-read Collection<int, InstagramPost> $posts
 * @property-read Collection<int, InstagramMediaRef> $mediaRefs
 */
class InstagramAccount extends Model
{
    protected $table = 'instagram_accounts';

    protected $guarded = [];

    /**
     * @return HasMany<InstagramProfileSnapshot, $this>
     */
    public function profileSnapshots(): HasMany
    {
        return $this->hasMany(InstagramProfileSnapshot::class, 'instagram_account_id');
    }

    /**
     * @return HasMany<InstagramPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(InstagramPost::class, 'instagram_account_id');
    }

    /**
     * @return HasMany<InstagramMediaRef, $this>
     */
    public function mediaRefs(): HasMany
    {
        return $this->hasMany(InstagramMediaRef::class, 'instagram_account_id');
    }
}
