<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class MediaCollectionImportResultData
{
    /**
     * @param  array{
     *     source_dsn: string,
     *     volume: string|null,
     *     path_prefix: string|null,
     *     files_inspected: int,
     *     files_imported: int,
     *     files_reobserved: int,
     *     volumes: list<string>,
     * }  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
