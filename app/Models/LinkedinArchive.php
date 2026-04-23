<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read LinkedinProfileSnapshot|null $profileSnapshot
 * @property-read Collection<int, LinkedinPosition> $positions
 * @property-read Collection<int, LinkedinEducationRecord> $educationRecords
 * @property-read Collection<int, LinkedinProject> $projects
 * @property-read Collection<int, LinkedinSkill> $skills
 * @property-read Collection<int, LinkedinLanguage> $languages
 * @property-read Collection<int, LinkedinConnection> $connections
 * @property-read Collection<int, LinkedinInvitation> $invitations
 * @property-read Collection<int, LinkedinRecommendation> $recommendations
 * @property-read Collection<int, LinkedinEndorsement> $endorsements
 * @property-read Collection<int, LinkedinConversation> $conversations
 * @property-read Collection<int, LinkedinMessage> $messages
 * @property-read Collection<int, LinkedinShare> $shares
 * @property-read Collection<int, LinkedinComment> $comments
 * @property-read Collection<int, LinkedinReaction> $reactions
 * @property-read Collection<int, LinkedinRichMedia> $richMedia
 */
class LinkedinArchive extends Model
{
    protected $table = 'linkedin_archives';

    protected $guarded = [];

    /**
     * @return HasOne<LinkedinProfileSnapshot, $this>
     */
    public function profileSnapshot(): HasOne
    {
        return $this->hasOne(LinkedinProfileSnapshot::class, 'linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(LinkedinPosition::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinEducationRecord, $this>
     */
    public function educationRecords(): HasMany
    {
        return $this->hasMany(LinkedinEducationRecord::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinProject, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(LinkedinProject::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinSkill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(LinkedinSkill::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(LinkedinLanguage::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinConnection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(LinkedinConnection::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(LinkedinInvitation::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(LinkedinRecommendation::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinEndorsement, $this>
     */
    public function endorsements(): HasMany
    {
        return $this->hasMany(LinkedinEndorsement::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinConversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(LinkedinConversation::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(LinkedinMessage::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(LinkedinShare::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(LinkedinComment::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(LinkedinReaction::class, 'first_seen_linkedin_archive_id');
    }

    /**
     * @return HasMany<LinkedinRichMedia, $this>
     */
    public function richMedia(): HasMany
    {
        return $this->hasMany(LinkedinRichMedia::class, 'first_seen_linkedin_archive_id');
    }
}
