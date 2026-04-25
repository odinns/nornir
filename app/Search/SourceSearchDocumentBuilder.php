<?php

declare(strict_types=1);

namespace App\Search;

use App\Data\Search\SearchDocumentData;

interface SourceSearchDocumentBuilder
{
    public function sourceType(): string;

    /**
     * @return iterable<SearchDocumentData>
     */
    public function build(): iterable;
}
