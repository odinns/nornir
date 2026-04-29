<?php

declare(strict_types=1);

namespace App\Data\Muninn;

use App\Models\Run;

final readonly class BiographyCandidateArtifactResultData
{
    public function __construct(
        public Run $run,
        public string $candidatePath,
        public int $sourceRunId,
        public int $evidenceRunId,
        public int $candidateCount,
    ) {}
}
