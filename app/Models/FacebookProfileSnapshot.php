<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facebook_archive_id
 * @property int|null $facebook_person_id
 * @property array<int, string>|null $emails_json
 * @property array<string, mixed>|null $raw_profile
 * @property-read FacebookArchive $archive
 * @property-read FacebookPerson|null $person
 */
class FacebookProfileSnapshot extends Model
{
    protected $table = 'facebook_profile_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'emails_json' => 'array',
            'raw_profile' => 'array',
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
