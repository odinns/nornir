<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $source_locator
 * @property string $access_mode
 * @property string $driver
 * @property string $database_name
 * @property array<string, mixed>|null $scope_snapshot
 * @property-read Collection<int, FidonetArea> $areas
 * @property-read Collection<int, FidonetMessage> $messages
 * @property-read Collection<int, FidonetAreaObservation> $areaObservations
 * @property-read Collection<int, FidonetThreadObservation> $threadObservations
 * @property-read Collection<int, FidonetMessageObservation> $messageObservations
 */
class FidonetSource extends Model
{
    protected $table = 'fidonet_sources';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope_snapshot' => 'array',
        ];
    }

    /**
     * @return HasMany<FidonetArea, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(FidonetArea::class, 'fidonet_source_id');
    }

    /**
     * @return HasMany<FidonetMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(FidonetMessage::class, 'fidonet_source_id');
    }

    /**
     * @return HasMany<FidonetAreaObservation, $this>
     */
    public function areaObservations(): HasMany
    {
        return $this->hasMany(FidonetAreaObservation::class, 'fidonet_source_id');
    }

    /**
     * @return HasMany<FidonetThreadObservation, $this>
     */
    public function threadObservations(): HasMany
    {
        return $this->hasMany(FidonetThreadObservation::class, 'fidonet_source_id');
    }

    /**
     * @return HasMany<FidonetMessageObservation, $this>
     */
    public function messageObservations(): HasMany
    {
        return $this->hasMany(FidonetMessageObservation::class, 'fidonet_source_id');
    }
}
