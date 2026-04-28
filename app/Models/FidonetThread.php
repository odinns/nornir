<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $area_code
 * @property string $derived_thread_key
 * @property string $source_method
 * @property int $message_count
 * @property bool $is_synthetic
 * @property string|null $confidence
 * @property-read Collection<int, FidonetThreadMessage> $threadMessages
 */
class FidonetThread extends Model
{
    protected $table = 'fidonet_threads';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
            'is_synthetic' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FidonetThreadMessage, $this>
     */
    public function threadMessages(): HasMany
    {
        return $this->hasMany(FidonetThreadMessage::class, 'fidonet_thread_id');
    }
}
