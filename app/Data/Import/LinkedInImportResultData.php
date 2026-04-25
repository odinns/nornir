<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class LinkedInImportResultData
{
    /**
     * @param  array{source_file:string, source_set_id:int, profile_snapshots:int, positions:int, education_records:int, projects:int, skills:int, languages:int, people:int, connections:int, invitations:int, recommendations:int, endorsements:int, shares:int, comments:int, reactions:int, rich_media:int, conversations:int, messages:int, attachments:int, inserted_messages:int, reobserved_messages:int}  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
