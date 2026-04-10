<?php

declare(strict_types=1);

namespace App\Data\Shared;

final readonly class RecordArtifactData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $runId,
        public string $artifactKind,
        public string $locator,
        public string $classification,
        public array $metadata = [],
    ) {}
}
