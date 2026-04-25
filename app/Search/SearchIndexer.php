<?php

declare(strict_types=1);

namespace App\Search;

interface SearchIndexer
{
    public function flush(?string $sourceType = null): void;

    public function import(?string $sourceType = null): void;
}
