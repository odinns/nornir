<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $twitter_archive_id
 * @property string|null $account_id
 * @property string|null $screen_name
 * @property string|null $display_name
 * @property string|null $bio
 * @property string|null $location
 * @property string|null $website_url
 * @property string|null $avatar_path
 * @property string|null $header_path
 * @property bool|null $is_verified
 * @property bool|null $is_verified_organization
 * @property array<string, mixed>|null $raw_profile
 */
class TwitterProfileSnapshot extends Model
{
    protected $table = 'twitter_profile_snapshots';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_verified_organization' => 'boolean',
            'raw_profile' => 'array',
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
