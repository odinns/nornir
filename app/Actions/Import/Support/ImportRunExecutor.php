<?php

declare(strict_types=1);

namespace App\Actions\Import\Support;

use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\StartRunData;
use App\Models\Run;
use App\Services\Nornir\RunRecorder;
use Throwable;

class ImportRunExecutor
{
    public function __construct(
        private readonly RunRecorder $runRecorder,
    ) {}

    /**
     * @param  callable(Run): array<string, mixed>  $import
     * @param  callable(Run, array<string, mixed>): void  $writeArtifacts
     * @return array{run:Run, summary:array<string, mixed>}
     */
    public function execute(
        ImporterDispatchData $dispatchPayload,
        string $operation,
        callable $import,
        callable $writeArtifacts,
    ): array {
        $run = $this->runRecorder->start(new StartRunData(
            subsystem: 'import',
            operation: $operation,
            inputScope: [
                'source_locator' => $dispatchPayload->sourceLocator,
                'scope_snapshot' => $dispatchPayload->scopeSnapshot,
            ],
            idempotencyKey: $this->makeIdempotencyKey($dispatchPayload, $operation),
        ));

        try {
            $summary = $import($run);
            $writeArtifacts($run, $summary);

            return [
                'run' => $this->runRecorder->complete($run),
                'summary' => $summary,
            ];
        } catch (Throwable $throwable) {
            $this->runRecorder->fail($run, $throwable->getMessage());

            throw $throwable;
        }
    }

    public function makeIdempotencyKey(ImporterDispatchData $dispatchPayload, string $operation): string
    {
        return $operation.':'.sha1($dispatchPayload->sourceLocator.'|'.json_encode($dispatchPayload->scopeSnapshot));
    }
}
