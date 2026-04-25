<?php

declare(strict_types=1);

namespace App\Data\Import;

use App\Models\Run;

final readonly class InstagramImportResultData
{
    /**
     * @param  array{username:string, posts:int, inserted_posts:int, reobserved_posts:int, media_refs:int, profile_photos:int, stories:int, stories_skipped:bool}  $summary
     */
    public function __construct(
        public Run $run,
        public array $summary,
    ) {}
}
