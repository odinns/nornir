<?php

declare(strict_types=1);

namespace App\Data\Shared;

final readonly class WriteProvenanceLinkData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $runId,
        public string $outputTarget,
        public string $claimKey,
        public string $evidenceType,
        public string $evidenceRef,
        public array $metadata = [],
    ) {}
}
