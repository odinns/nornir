<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $account_key
 * @property string $account_email
 * @property string|null $display_name
 * @property string $access_mode
 * @property-read Collection<int, GmailThread> $threads
 */
class GmailAccount extends Model
{
    protected $table = 'gmail_accounts';

    protected $guarded = [];

    /**
     * @return HasMany<GmailThread, $this>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(GmailThread::class);
    }
}
