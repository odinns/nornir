<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class AppleHealthImportResultData
{
    /**
     * @param  array{
     *     source_file:string,
     *     source_set_id:int,
     *     records:int,
     *     workouts:int,
     *     inserted_records:int,
     *     reobserved_records:int,
     *     inserted_workouts:int,
     *     reobserved_workouts:int
     * }  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
