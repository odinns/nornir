<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $canonical_key
 * @property string $record_type
 * @property string|null $source_name
 * @property string|null $source_version
 * @property string|null $unit
 * @property string|null $value
 * @property CarbonImmutable|null $creation_at
 * @property CarbonImmutable|null $start_at
 * @property CarbonImmutable|null $end_at
 * @property array<string, mixed>|null $raw_record
 */
class AppleHealthRecord extends Model
{
    protected $table = 'apple_health_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'creation_at' => 'immutable_datetime',
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'raw_record' => 'array',
        ];
    }
}
