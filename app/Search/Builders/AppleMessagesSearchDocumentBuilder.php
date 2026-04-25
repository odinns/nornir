<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\AppleMessagesMessage;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class AppleMessagesSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'apple-messages';
    }

    public function build(): iterable
    {
        foreach (AppleMessagesMessage::query()->with(['conversation.participants', 'sender'])->lazyById() as $message) {
            $conversation = $message->conversation;

            yield new SearchDocumentData(
                sourceType: 'apple-messages',
                sourceTable: 'apple_messages_messages',
                sourceId: $message->canonical_key,
                title: $conversation->display_name ?? $conversation->room_name ?? $message->group_title,
                body: $message->text_body,
                occurredAt: $message->sent_at,
                participants: $this->participants(
                    $conversation->participants->pluck('display_name')
                        ->merge($conversation->participants->pluck('identifier'))
                        ->push($message->sender?->display_name)
                        ->push($message->sender?->identifier)
                        ->all(),
                ),
                metadata: [
                    'from_me' => $message->from_me,
                    'service' => $message->service,
                    'conversation_key' => $conversation->conversation_key,
                ],
            );
        }
    }
}
