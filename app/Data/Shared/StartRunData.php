<?php

declare(strict_types=1);

namespace App\Data\Shared;

final readonly class StartRunData
{
    /**
     * @param  array<string, mixed>  $inputScope
     */
    public function __construct(
        public string $subsystem,
        public string $operation,
        public array $inputScope,
        public string $idempotencyKey,
        public ?int $parentRunId = null,
    ) {}
}
