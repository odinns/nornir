<?php

declare(strict_types=1);

namespace App\Services\Nornir;

use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\ProvenanceLink;

class ProvenanceWriter
{
    public function link(WriteProvenanceLinkData $data): ProvenanceLink
    {
        $link = ProvenanceLink::query()->firstOrCreate(
            [
                'run_id' => $data->runId,
                'output_target' => $data->outputTarget,
                'claim_key' => $data->claimKey,
                'evidence_type' => $data->evidenceType,
                'evidence_ref' => $data->evidenceRef,
            ],
            [
                'metadata' => $data->metadata,
            ],
        );

        $link->refresh();

        return $link;
    }
}
