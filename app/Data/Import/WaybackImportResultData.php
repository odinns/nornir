<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class WaybackImportResultData
{
    /**
     * @param  array{source_file:string, scope_id:int, cdx_captures:int, captures:int, accepted:int, rejected:int, failed:int, screenshots:int, mirrors:int}  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
