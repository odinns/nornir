<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\FidonetMessage;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class FidonetSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'fidonet';
    }

    public function build(): iterable
    {
        foreach (FidonetMessage::query()->with('cleanup')->lazyById() as $message) {
            yield new SearchDocumentData(
                sourceType: 'fidonet',
                sourceTable: 'fidonet_messages',
                sourceId: $message->canonical_message_id,
                title: $message->subject,
                body: $message->cleanup?->cleaned_authored_text,
                occurredAt: $message->posted_at ?? $message->arrived_at,
                participants: $this->participants([
                    $message->from_name,
                    $message->from_address,
                    $message->to_name,
                    $message->to_address,
                ]),
                metadata: ['area_code' => $message->area_code],
            );
        }
    }
}
