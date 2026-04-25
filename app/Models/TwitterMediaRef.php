<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $twitter_archive_id
 * @property string|null $account_id
 * @property string $media_key
 * @property string $owner_type
 * @property string $owner_id
 * @property string $source_surface
 * @property string|null $relative_path
 * @property string|null $source_url
 * @property string|null $media_type
 * @property array<string, mixed>|null $raw_media
 */
class TwitterMediaRef extends Model
{
    protected $table = 'twitter_media_refs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_media' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TwitterArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(TwitterArchive::class, 'twitter_archive_id');
    }
}
