<?php

declare(strict_types=1);

namespace App\Services\Nornir;

use App\Data\Shared\RecordArtifactData;
use App\Models\RunArtifact;

class ArtifactRecorder
{
    public function record(RecordArtifactData $data): RunArtifact
    {
        $artifact = RunArtifact::query()->firstOrCreate(
            [
                'run_id' => $data->runId,
                'artifact_kind' => $data->artifactKind,
                'locator' => $data->locator,
                'classification' => $data->classification,
            ],
            [
                'metadata' => $data->metadata,
            ],
        );

        $artifact->refresh();

        return $artifact;
    }
}
