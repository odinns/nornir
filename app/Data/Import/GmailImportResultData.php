<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class GmailImportResultData
{
    /**
     * @param  array{source_set_id:int, account_email:string, threads:int, messages:int, inserted_messages:int, reobserved_messages:int, labels:int, attachments:int}  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
