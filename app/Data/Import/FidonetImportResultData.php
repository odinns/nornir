<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class FidonetImportResultData
{
    /**
     * @param  array<string, int|string>  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
