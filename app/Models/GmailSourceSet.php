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
 * @property string $account_email
 * @property string $query
 * @property-read Collection<int, GmailMessageObservation> $messageObservations
 */
class GmailSourceSet extends Model
{
    protected $table = 'gmail_source_sets';

    protected $guarded = [];

    /**
     * @return HasMany<GmailMessageObservation, $this>
     */
    public function messageObservations(): HasMany
    {
        return $this->hasMany(GmailMessageObservation::class, 'gmail_source_set_id');
    }
}
