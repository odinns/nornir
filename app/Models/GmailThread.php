<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $gmail_account_id
 * @property string $thread_id
 * @property string|null $snippet
 * @property string|null $history_id
 * @property array<string, mixed>|null $raw_thread
 * @property-read GmailAccount $account
 * @property-read Collection<int, GmailMessage> $messages
 */
class GmailThread extends Model
{
    protected $table = 'gmail_threads';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_thread' => 'array',
        ];
    }

    /**
     * @return BelongsTo<GmailAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(GmailAccount::class, 'gmail_account_id');
    }

    /**
     * @return HasMany<GmailMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(GmailMessage::class, 'gmail_thread_id');
    }
}
