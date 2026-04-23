<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $display_name
 * @property-read Collection<int, LinkedinProfileSnapshot> $profileSnapshots
 * @property-read Collection<int, LinkedinConnection> $connections
 * @property-read Collection<int, LinkedinInvitation> $sentInvitations
 * @property-read Collection<int, LinkedinInvitation> $receivedInvitations
 * @property-read Collection<int, LinkedinRecommendation> $recommendations
 * @property-read Collection<int, LinkedinEndorsement> $endorsements
 * @property-read Collection<int, LinkedinMessage> $messagesSent
 */
class LinkedinPerson extends Model
{
    protected $table = 'linkedin_people';

    protected $guarded = [];

    /**
     * @return HasMany<LinkedinProfileSnapshot, $this>
     */
    public function profileSnapshots(): HasMany
    {
        return $this->hasMany(LinkedinProfileSnapshot::class, 'linkedin_person_id');
    }

    /**
     * @return HasMany<LinkedinConnection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(LinkedinConnection::class, 'linkedin_person_id');
    }

    /**
     * @return HasMany<LinkedinInvitation, $this>
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(LinkedinInvitation::class, 'sender_linkedin_person_id');
    }

    /**
     * @return HasMany<LinkedinInvitation, $this>
     */
    public function receivedInvitations(): HasMany
    {
        return $this->hasMany(LinkedinInvitation::class, 'recipient_linkedin_person_id');
    }

    /**
     * @return HasMany<LinkedinRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(LinkedinRecommendation::class, 'counterpart_linkedin_person_id');
    }

    /**
     * @return HasMany<LinkedinEndorsement, $this>
     */
    public function endorsements(): HasMany
    {
        return $this->hasMany(LinkedinEndorsement::class, 'counterpart_linkedin_person_id');
    }

    /**
     * @return HasMany<LinkedinMessage, $this>
     */
    public function messagesSent(): HasMany
    {
        return $this->hasMany(LinkedinMessage::class, 'sender_linkedin_person_id');
    }
}
