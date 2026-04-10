<?php

declare(strict_types=1);

namespace App\Actions\Intake;

use App\Data\Intake\ImporterDispatchData;
use App\Data\Intake\RecordIntakeData;
use App\Data\Intake\RecordIntakeResultData;
use App\Models\IntakeRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordIntakeAction
{
    public function __invoke(RecordIntakeData $data): RecordIntakeResultData
    {
        $sourceConfig = config("intake.sources.{$data->sourceType}");

        if (! is_array($sourceConfig)) {
            throw new InvalidArgumentException("Unsupported source type [{$data->sourceType}].");
        }

        $allowedAccessModes = $sourceConfig['access_modes'] ?? [];

        if (! in_array($data->accessMode, $allowedAccessModes, true)) {
            throw new InvalidArgumentException(
                "Access mode [{$data->accessMode}] is not allowed for source type [{$data->sourceType}]."
            );
        }

        $this->assertReachable($data);

        $intakeRecord = IntakeRecord::query()->create([
            'source_type' => $data->sourceType,
            'access_mode' => $data->accessMode,
            'source_locator' => $data->sourceLocator,
            'scope_snapshot' => $data->scopeSnapshot,
            'importer_options' => $data->importerOptions,
        ]);

        $dispatchPayload = new ImporterDispatchData(
            intakeRecordId: $intakeRecord->id,
            sourceType: $intakeRecord->source_type,
            accessMode: $intakeRecord->access_mode,
            sourceLocator: $data->sourceLocator,
            scopeSnapshot: $intakeRecord->scope_snapshot ?? [],
            importerOptions: $intakeRecord->importer_options ?? [],
            importerKey: (string) $sourceConfig['importer_key'],
        );

        $reviewManifestPath = $this->writeReviewManifest($intakeRecord, $dispatchPayload);

        return new RecordIntakeResultData(
            intakeRecord: $intakeRecord,
            dispatchPayload: $dispatchPayload,
            reviewManifestPath: $reviewManifestPath,
        );
    }

    private function assertReachable(RecordIntakeData $data): void
    {
        if (! File::exists($data->sourceLocator)) {
            throw new InvalidArgumentException('Source locator is not reachable.');
        }

        if ($data->accessMode === 'archive' && ! File::isFile($data->sourceLocator)) {
            throw new InvalidArgumentException('Archive intake requires a file source locator.');
        }

        if ($data->accessMode === 'local-path' && ! File::isDirectory($data->sourceLocator)) {
            throw new InvalidArgumentException('Local path intake requires a directory source locator.');
        }

        $acceptedRootPaths = $data->scopeSnapshot['accepted_root_paths'] ?? null;

        if (! is_array($acceptedRootPaths)) {
            return;
        }

        foreach ($acceptedRootPaths as $acceptedRootPath) {
            if (! is_string($acceptedRootPath) || ! File::exists($acceptedRootPath)) {
                throw new InvalidArgumentException('Source locator is not reachable.');
            }
        }
    }

    private function writeReviewManifest(
        IntakeRecord $intakeRecord,
        ImporterDispatchData $dispatchPayload,
    ): string {
        $directory = base_path('data/intake');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/intake-'.$intakeRecord->id.'-'.Str::slug($intakeRecord->source_type).'.json';

        $manifest = [
            'requested' => [
                'source_type' => $intakeRecord->source_type,
                'access_mode' => $intakeRecord->access_mode,
                'source_locator' => $dispatchPayload->sourceLocator,
            ],
            'boundary' => [
                'source_locator' => $dispatchPayload->sourceLocator,
                'scope_snapshot' => $dispatchPayload->scopeSnapshot,
            ],
            'handoff' => [
                'importer_key' => $dispatchPayload->importerKey,
                'intake_record_id' => $dispatchPayload->intakeRecordId,
            ],
        ];

        File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $path;
    }
}
