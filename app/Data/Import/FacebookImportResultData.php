<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class FacebookImportResultData
{
    /**
     * @param  array{source_file:string, source_set_id:int, people:int, profile_snapshots:int, social_edges:int, threads:int, messages:int, posts:int, comments:int, reactions:int, attachments:int, inserted_messages:int, reobserved_messages:int}  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
