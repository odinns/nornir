<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $canonical_message_id
 * @property int $fidonet_participant_id
 * @property string $role
 * @property-read FidonetParticipant $participant
 * @property-read FidonetMessage|null $message
 */
class FidonetMessageParticipant extends Model
{
    protected $table = 'fidonet_message_participants';

    protected $guarded = [];

    /**
     * @return BelongsTo<FidonetParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(FidonetParticipant::class, 'fidonet_participant_id');
    }

    /**
     * @return BelongsTo<FidonetMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(FidonetMessage::class, 'canonical_message_id', 'canonical_message_id');
    }
}
