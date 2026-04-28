<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $thread_path
 * @property CarbonImmutable|null $first_message_at
 * @property CarbonImmutable|null $last_message_at
 * @property-read FacebookArchive $archive
 * @property-read Collection<int, FacebookMessage> $messages
 * @property-read Collection<int, FacebookPerson> $participants
 */
class FacebookThread extends Model
{
    protected $table = 'facebook_threads';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_still_participant' => 'boolean',
            'message_count' => 'integer',
            'first_message_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'raw_thread' => 'array',
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
     * @return HasMany<FacebookMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(FacebookMessage::class, 'facebook_thread_id');
    }

    /**
     * @return BelongsToMany<FacebookPerson, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(
            FacebookPerson::class,
            'facebook_thread_participants',
            'facebook_thread_id',
            'facebook_person_id',
        );
    }
}
