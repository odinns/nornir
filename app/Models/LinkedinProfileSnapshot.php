<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $birth_date
 * @property CarbonImmutable|null $registered_at
 * @property array<int, string>|null $emails_json
 * @property array<int, string>|null $phone_numbers_json
 * @property array<int, string>|null $whatsapp_numbers_json
 * @property array<string, mixed>|null $raw_profile
 * @property-read LinkedinArchive $archive
 * @property-read LinkedinPerson|null $person
 */
class LinkedinProfileSnapshot extends Model
{
    protected $table = 'linkedin_profile_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'immutable_date',
            'registered_at' => 'immutable_datetime',
            'emails_json' => 'array',
            'phone_numbers_json' => 'array',
            'whatsapp_numbers_json' => 'array',
            'raw_profile' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LinkedinArchive, $this>
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(LinkedinArchive::class, 'linkedin_archive_id');
    }

    /**
     * @return BelongsTo<LinkedinPerson, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(LinkedinPerson::class, 'linkedin_person_id');
    }
}
