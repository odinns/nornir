<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\GmailMessage;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class GmailSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'gmail';
    }

    public function build(): iterable
    {
        foreach (GmailMessage::query()->lazyById() as $message) {
            yield new SearchDocumentData(
                sourceType: 'gmail',
                sourceTable: 'gmail_messages',
                sourceId: $message->message_id,
                title: $message->subject,
                body: $message->body_plain ?? $message->snippet,
                occurredAt: $message->message_received_at,
                participants: $this->participants([$message->from_header, $message->to_header, $message->cc_header]),
                metadata: ['gmail_thread_id' => $message->gmail_thread_id],
            );
        }
    }
}
