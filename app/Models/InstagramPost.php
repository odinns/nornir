<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $instagram_account_id
 * @property string $post_key
 * @property string|null $caption
 * @property int|null $post_timestamp
 * @property int $media_count
 * @property array<string, mixed>|null $raw_payload
 * @property-read InstagramAccount $account
 * @property-read Collection<int, InstagramMediaRef> $mediaRefs
 */
class InstagramPost extends Model
{
    protected $table = 'instagram_posts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'post_timestamp' => 'integer',
            'media_count' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<InstagramAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }

    /**
     * @return HasMany<InstagramMediaRef, $this>
     */
    public function mediaRefs(): HasMany
    {
        return $this->hasMany(InstagramMediaRef::class, 'instagram_post_id');
    }
}
