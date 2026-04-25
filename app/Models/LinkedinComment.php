<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property CarbonImmutable|null $commented_at
 * @property array<string, mixed>|null $raw_comment
 */
class LinkedinComment extends Model
{
    protected $table = 'linkedin_comments';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'commented_at' => 'immutable_datetime',
            'raw_comment' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LinkedinArchive, $this>
     */
    public function firstSeenArchive(): BelongsTo
    {
        return $this->belongsTo(LinkedinArchive::class, 'first_seen_linkedin_archive_id');
    }
}
