<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class ChatGptImportResultData
{
    /**
     * @param  array{source_file:string, conversations:int, messages:int}  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
