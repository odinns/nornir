<?php

declare(strict_types=1);

namespace App\Data\Intake;

final readonly class RecordIntakeData
{
    /**
     * @param  array<string, mixed>  $scopeSnapshot
     * @param  array<string, mixed>  $importerOptions
     */
    public function __construct(
        public string $sourceType,
        public string $accessMode,
        public string $sourceLocator,
        public array $scopeSnapshot,
        public array $importerOptions,
    ) {}
}
