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
 * @property array<string, mixed> $cdx_fields
 * @property array<string, mixed> $retrieval_metadata
 * @property array<string, mixed> $raw_cdx_json
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
