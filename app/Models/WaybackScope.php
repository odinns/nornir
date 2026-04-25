<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $scope
 * @property string $match_mode
 * @property array<string, mixed> $filter_policy
 */
class WaybackScope extends Model
{
    protected $guarded = [];

    /**
     * @return HasMany<WaybackCapture, $this>
     */
    public function captures(): HasMany
    {
        return $this->hasMany(WaybackCapture::class);
    }

    protected function casts(): array
    {
        return [
            'filter_policy' => 'array',
        ];
    }
}
