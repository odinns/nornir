<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $identifier
 * @property string|null $service
 * @property string|null $uncanonicalized_identifier
 * @property string|null $display_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AppleMessagesMessage> $sentMessages
 */
class AppleMessagesParticipant extends Model
{
    protected $table = 'apple_messages_participants';

    protected $guarded = [];

    /**
     * @return HasMany<AppleMessagesMessage, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(AppleMessagesMessage::class, 'sender_participant_id');
    }
}
