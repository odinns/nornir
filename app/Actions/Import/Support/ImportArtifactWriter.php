<?php

declare(strict_types=1);

namespace App\Actions\Import\Support;

use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\RecordArtifactData;
use App\Models\Run;
use App\Services\Nornir\ArtifactRecorder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportArtifactWriter
{
    public function __construct(
        private readonly ArtifactRecorder $artifactRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     */
    public function write(
        Run $run,
        ImporterDispatchData $dispatchPayload,
        string $sourceType,
        string $artifactKind,
        array $summary,
    ): void {
        $importDirectory = base_path('data/imports/'.$sourceType);
        $runDirectory = base_path('data/runs/import');
        File::ensureDirectoryExists($importDirectory);
        File::ensureDirectoryExists($runDirectory);

        $sourceFile = $summary['source_file'] ?? $sourceType.'-import';
        $sourceFileSlug = is_string($sourceFile)
            ? Str::slug(pathinfo($sourceFile, PATHINFO_FILENAME))
            : $sourceType.'-import';

        $importSummaryPath = $importDirectory.'/'.$sourceFileSlug.'-summary.json';
        $runSummaryPath = $runDirectory.'/'.$sourceType.'-import-run-'.$run->id.'.json';

        $payload = [
            'source_locator' => $dispatchPayload->sourceLocator,
            'scope_snapshot' => $dispatchPayload->scopeSnapshot,
            'summary' => $summary,
        ];

        File::put($importSummaryPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->writeRunSummaryFile($runSummaryPath, $run, $summary);

        $this->artifactRecorder->record(new RecordArtifactData(
            runId: $run->id,
            artifactKind: $artifactKind,
            locator: $importSummaryPath,
            classification: 'diagnostic',
            metadata: $summary,
        ));

        $this->artifactRecorder->record(new RecordArtifactData(
            runId: $run->id,
            artifactKind: 'run-summary',
            locator: $runSummaryPath,
            classification: 'diagnostic',
            metadata: $summary,
        ));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function refreshRunSummary(Run $run, string $sourceType, array $summary): void
    {
        $runDirectory = base_path('data/runs/import');
        File::ensureDirectoryExists($runDirectory);

        $runSummaryPath = $runDirectory.'/'.$sourceType.'-import-run-'.$run->id.'.json';

        $this->writeRunSummaryFile($runSummaryPath, $run, $summary);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function writeRunSummaryFile(string $path, Run $run, array $summary): void
    {
        File::put($path, json_encode([
            'run_id' => $run->id,
            'status' => $run->status,
            'summary' => $summary,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
