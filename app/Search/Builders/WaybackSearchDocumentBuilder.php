<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\WaybackCapture;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class WaybackSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'wayback';
    }

    public function build(): iterable
    {
        foreach (WaybackCapture::query()->lazyById() as $capture) {
            yield new SearchDocumentData(
                sourceType: 'wayback',
                sourceTable: 'wayback_captures',
                sourceId: (string) $capture->id,
                title: $capture->title,
                body: $this->joinText([$capture->meta_description, $capture->extracted_authored_text, $capture->evidence_summary]),
                occurredAt: $capture->captured_at,
                urlOrLocator: $capture->original_url,
                metadata: ['verdict' => $capture->verdict, 'page_key' => $capture->page_key],
            );
        }
    }
}
