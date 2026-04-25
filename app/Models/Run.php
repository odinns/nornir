<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $subsystem
 * @property string $operation
 * @property string $status
 * @property array<string, mixed> $input_scope
 * @property string $idempotency_key
 * @property int|null $parent_run_id
 * @property string|null $failure_summary
 */
class Run extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PARTIALLY_COMPLETED = 'partially_completed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_scope' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Run, $this>
     */
    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_run_id');
    }

    /**
     * @return HasMany<Run, $this>
     */
    public function childRuns(): HasMany
    {
        return $this->hasMany(self::class, 'parent_run_id');
    }

    /**
     * @return HasMany<RunEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RunEvent::class);
    }

    /**
     * @return HasMany<RunArtifact, $this>
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(RunArtifact::class);
    }

    /**
     * @return HasMany<ProvenanceLink, $this>
     */
    public function provenanceLinks(): HasMany
    {
        return $this->hasMany(ProvenanceLink::class);
    }
}
