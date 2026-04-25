<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class TwitterImportResultData
{
    /**
     * @param  array{source_file:string, source_set_id:int, accounts:int, profile_snapshots:int, tweets:int, note_tweets:int, media_refs:int, inserted_tweets:int, reobserved_tweets:int}  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
