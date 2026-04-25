<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $chatgpt_message_id
 * @property int $part_index
 * @property string $part_type
 * @property string|null $text_part
 * @property string|null $asset_pointer
 * @property array<string, mixed>|null $raw_part
 * @property-read ChatGptMessage $message
 */
class ChatGptMessagePart extends Model
{
    protected $table = 'chatgpt_message_parts';

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'part_index' => 'integer',
            'raw_part' => 'array',
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
