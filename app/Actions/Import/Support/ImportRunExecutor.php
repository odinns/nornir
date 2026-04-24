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
            $completedRun = $this->runRecorder->complete($run);
            $this->refreshRunSummaryArtifact($completedRun, $operation, $summary);

            return [
                'run' => $completedRun,
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

    /**
     * @param  array<string, mixed>  $summary
     */
    private function refreshRunSummaryArtifact(Run $run, string $operation, array $summary): void
    {
        if (! str_ends_with($operation, '-import')) {
            return;
        }

        $sourceType = substr($operation, 0, -strlen('-import'));

        if ($sourceType === '') {
            return;
        }

        app(ImportArtifactWriter::class)->refreshRunSummary($run, $sourceType, $summary);
    }
}
