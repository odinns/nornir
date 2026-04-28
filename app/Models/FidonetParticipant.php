<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $participant_key
 * @property string $display_name
 * @property string|null $address
 * @property bool $is_odinn_candidate
 * @property-read Collection<int, FidonetMessageParticipant> $messageParticipants
 */
class FidonetParticipant extends Model
{
    protected $table = 'fidonet_participants';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_odinn_candidate' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FidonetMessageParticipant, $this>
     */
    public function messageParticipants(): HasMany
    {
        return $this->hasMany(FidonetMessageParticipant::class, 'fidonet_participant_id');
    }
}
