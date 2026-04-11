<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class SmsImportResultData
{
    /**
     * @param  array{
     *     source_file:string,
     *     source_set_id:int,
     *     conversations:int,
     *     participants:int,
     *     messages:int,
     *     attachments:int,
     *     inserted_messages:int,
     *     reobserved_messages:int
     * }  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
