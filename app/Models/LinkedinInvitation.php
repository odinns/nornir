<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property CarbonImmutable|null $sent_at
 * @property array<string, mixed>|null $raw_invitation
 */
class LinkedinInvitation extends Model
{
    protected $table = 'linkedin_invitations';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'raw_invitation' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LinkedinArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(LinkedinArchive::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return BelongsTo<LinkedinPerson, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(LinkedinPerson::class, 'sender_linkedin_person_id');
    }

    /**
     * @return BelongsTo<LinkedinPerson, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(LinkedinPerson::class, 'recipient_linkedin_person_id');
    }
}
