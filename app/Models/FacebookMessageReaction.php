<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facebook_message_id
 * @property int|null $facebook_person_id
 * @property string $reaction_key
 * @property string $reaction
 * @property-read FacebookMessage $message
 * @property-read FacebookPerson|null $person
 */
class FacebookMessageReaction extends Model
{
    protected $table = 'facebook_message_reactions';

    protected $guarded = [];

    /**
     * @return BelongsTo<FacebookMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(FacebookMessage::class, 'facebook_message_id');
    }

    /**
     * @return BelongsTo<FacebookPerson, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(FacebookPerson::class, 'facebook_person_id');
    }
}
