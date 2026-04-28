<?php

declare(strict_types=1);

namespace App\Data\Gmail;

use App\Models\Run;

final readonly class GmailImportantEvidenceBundleResultData
{
    /**
     * @param  list<int>  $sourceSetIds
     */
    public function __construct(
        public Run $run,
        public string $path,
        public int $sourceRunId,
        public int $matchedCount,
        public array $sourceSetIds,
    ) {}
}
