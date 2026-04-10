<?php

declare(strict_types=1);

namespace App\Data\Intake;

use App\Models\IntakeRecord;

final readonly class RecordIntakeResultData
{
    public function __construct(
        public IntakeRecord $intakeRecord,
        public ImporterDispatchData $dispatchPayload,
        public string $reviewManifestPath,
    ) {}
}
