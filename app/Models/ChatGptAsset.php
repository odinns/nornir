<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chatgpt_message_id
 * @property string $asset_pointer
 * @property string|null $asset_type
 * @property array<string, mixed>|null $raw_asset
 * @property-read ChatGptMessage $message
 */
class ChatGptAsset extends Model
{
    protected $table = 'chatgpt_assets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_asset' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ChatGptMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatGptMessage::class, 'chatgpt_message_id');
    }
}
