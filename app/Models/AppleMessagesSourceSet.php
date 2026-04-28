<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $source_key
 * @property string $source_locator
 * @property string $access_mode
 * @property string|null $attachments_root
 * @property-read Collection<int, AppleMessagesMessageObservation> $messageObservations
 */
class AppleMessagesSourceSet extends Model
{
    protected $table = 'apple_messages_source_sets';

    protected $guarded = [];

    /**
     * @return HasMany<AppleMessagesMessageObservation, $this>
     */
    public function messageObservations(): HasMany
    {
        return $this->hasMany(AppleMessagesMessageObservation::class, 'apple_messages_source_set_id');
    }
}
