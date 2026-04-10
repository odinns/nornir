<?php

declare(strict_types=1);

namespace App\Data\Import;

final readonly class WikiCompilationHandoffData
{
    /**
     * @param  array<string, mixed>  $canonicalScope
     */
    public function __construct(
        public string $sourceType,
        public string $handoffType,
        public int $owningRunId,
        public array $canonicalScope,
    ) {}
}
