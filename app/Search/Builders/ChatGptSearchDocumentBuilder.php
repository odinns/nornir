<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\ChatGptConversation;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class ChatGptSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'chatgpt';
    }

    public function build(): iterable
    {
        foreach (ChatGptConversation::query()->with(['messages.parts'])->lazyById() as $conversation) {
            $messages = $conversation->messages->sortBy('message_created_at');
            $body = $this->joinText(
                $messages
                    ->flatMap(fn ($message) => $message->parts->sortBy('part_index')->pluck('text_part'))
                    ->all(),
            );

            yield new SearchDocumentData(
                sourceType: 'chatgpt',
                sourceTable: 'chatgpt_conversations',
                sourceId: $conversation->conversation_id,
                title: $conversation->title,
                body: $body,
                occurredAt: $conversation->conversation_created_at ?? $conversation->conversation_updated_at,
                participants: $this->participants($messages->pluck('author_name')->merge($messages->pluck('author_role'))->all()),
                metadata: ['message_count' => $messages->count()],
            );
        }
    }
}
