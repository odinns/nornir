<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class InstagramImportResultData
{
    /**
     * @param  array<string, int|string|bool>  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
