<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $wayback_scope_id
 * @property string $timestamp
 * @property CarbonImmutable $captured_at
 * @property string $original_url
 * @property string $replay_url
 * @property array<string, mixed> $cdx_fields
 * @property string $verdict
 * @property string|null $reject_reason
 * @property string|null $raw_replay_html
 * @property string|null $extracted_authored_text
 * @property string|null $title
 * @property string|null $meta_description
 * @property array<string, mixed> $retrieval_metadata
 * @property string|null $screenshot_path
 * @property string|null $screenshot_hash
 * @property string|null $mirror_path
 * @property array<string, mixed> $raw_cdx_json
 * @property string|null $biographical_surface
 * @property string|null $evidence_summary
 */
class WaybackCapture extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<WaybackScope, $this>
     */
    public function scope(): BelongsTo
    {
        return $this->belongsTo(WaybackScope::class, 'wayback_scope_id');
    }

    protected function casts(): array
    {
        return [
            'captured_at' => 'immutable_datetime',
            'cdx_fields' => 'array',
            'retrieval_metadata' => 'array',
            'raw_cdx_json' => 'array',
            'timeline_anchor_date' => 'date',
        ];
    }
}
