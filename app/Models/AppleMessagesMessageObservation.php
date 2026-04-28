<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $apple_messages_message_id
 * @property int $apple_messages_source_set_id
 * @property int|null $source_message_row_id
 * @property-read AppleMessagesMessage $message
 * @property-read AppleMessagesSourceSet $sourceSet
 */
class AppleMessagesMessageObservation extends Model
{
    protected $table = 'apple_messages_message_observations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_message_row_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AppleMessagesMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AppleMessagesMessage::class, 'apple_messages_message_id');
    }

    /**
     * @return BelongsTo<AppleMessagesSourceSet, $this>
     */
    public function sourceSet(): BelongsTo
    {
        return $this->belongsTo(AppleMessagesSourceSet::class, 'apple_messages_source_set_id');
    }
}
