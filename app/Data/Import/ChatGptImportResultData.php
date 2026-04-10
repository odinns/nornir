<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class ChatGptImportResultData
{
    public function __construct(
        public Run $run,
    ) {}
}
