<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $instagram_account_id
 * @property int|null $instagram_post_id
 * @property string $media_ref_key
 * @property string $uri
 * @property string $media_type
 * @property int|null $creation_timestamp
 * @property string|null $title
 * @property-read InstagramAccount $account
 * @property-read InstagramPost|null $post
 */
class InstagramMediaRef extends Model
{
    protected $table = 'instagram_media_refs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'creation_timestamp' => 'integer',
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
     * @return BelongsTo<InstagramPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(InstagramPost::class, 'instagram_post_id');
    }
}
