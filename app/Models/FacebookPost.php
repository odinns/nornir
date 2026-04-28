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
 * @property string $canonical_key
 * @property int|null $published_timestamp
 * @property CarbonImmutable|null $published_at
 * @property string|null $title
 * @property string|null $content
 * @property-read FacebookArchive $archive
 * @property-read Collection<int, FacebookPostObservation> $observations
 * @property-read Collection<int, FacebookAttachment> $attachments
 */
class FacebookPost extends Model
{
    protected $table = 'facebook_posts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_timestamp' => 'integer',
            'published_at' => 'immutable_datetime',
            'raw_post' => 'array',
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
     * @return HasMany<FacebookPostObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(FacebookPostObservation::class, 'facebook_post_id');
    }

    /**
     * @return HasMany<FacebookAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(FacebookAttachment::class, 'facebook_post_id');
    }
}
