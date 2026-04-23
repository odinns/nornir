<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkedinSkill extends Model
{
    protected $table = 'linkedin_skills';

    protected $guarded = [];

    /**
     * @return BelongsTo<LinkedinArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(LinkedinArchive::class, 'first_seen_linkedin_archive_id');
    }
}
