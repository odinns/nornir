<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\AppleHealthImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use XMLReader;

class ImportAppleHealthAction
{
    private const int TRANSACTION_BATCH_SIZE = 1000;

    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly SourceObservationStore $sourceObservationStore,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload): AppleHealthImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'apple-health-import',
            import: fn (Run $run): array => $this->importExport($dispatchPayload, $run),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->writeArtifacts($run, $dispatchPayload, $summary);
            },
        );
        /** @var array{
         *     run:Run,
         *     summary:array{
         *         source_file:string,
         *         source_set_id:int,
         *         records:int,
         *         workouts:int,
         *         inserted_records:int,
         *         reobserved_records:int,
         *         inserted_workouts:int,
         *         reobserved_workouts:int
         *     }
         * } $execution
         */

        return new AppleHealthImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array{
     *     source_file:string,
     *     source_set_id:int,
     *     records:int,
     *     workouts:int,
     *     inserted_records:int,
     *     reobserved_records:int,
     *     inserted_workouts:int,
     *     reobserved_workouts:int
     * }
     */
    private function importExport(ImporterDispatchData $dispatchPayload, Run $run): array
    {
        $exportXmlPath = $this->resolveExportXmlPath($dispatchPayload);

        $sourceSetId = DB::transaction(function () use ($dispatchPayload, $exportXmlPath): int {
            return $this->sourceObservationStore->upsertAndReturnId(
                table: 'apple_health_source_sets',
                unique: [
                    'source_key' => sha1($dispatchPayload->sourceLocator),
                ],
                values: [
                    'source_locator' => $dispatchPayload->sourceLocator,
                    'access_mode' => $dispatchPayload->accessMode,
                    'export_xml_path' => $exportXmlPath,
                ],
            );
        });

        $summary = [
            'source_file' => basename($exportXmlPath),
            'source_set_id' => $sourceSetId,
            'records' => 0,
            'workouts' => 0,
            'inserted_records' => 0,
            'reobserved_records' => 0,
            'inserted_workouts' => 0,
            'reobserved_workouts' => 0,
        ];

        $batch = [];

        foreach ($this->streamEntries($exportXmlPath) as $entry) {
            $batch[] = $entry;

            if (count($batch) < self::TRANSACTION_BATCH_SIZE) {
                continue;
            }

            $this->importEntryBatch($batch, $exportXmlPath, $run, $sourceSetId, $summary);
            $batch = [];
        }

        if ($batch !== []) {
            $this->importEntryBatch($batch, $exportXmlPath, $run, $sourceSetId, $summary);
        }

        return $summary;
    }

    /**
     * @param  list<array{element:string, attributes:array<string, string>}>  $entries
     * @param  array{
     *     source_file:string,
     *     source_set_id:int,
     *     records:int,
     *     workouts:int,
     *     inserted_records:int,
     *     reobserved_records:int,
     *     inserted_workouts:int,
     *     reobserved_workouts:int
     * }  &$summary
     */
    private function importEntryBatch(
        array $entries,
        string $exportXmlPath,
        Run $run,
        int $sourceSetId,
        array &$summary,
    ): void {
        DB::transaction(function () use ($entries, $exportXmlPath, $run, $sourceSetId, &$summary): void {
            foreach ($entries as $entry) {
                if ($entry['element'] === 'Record') {
                    $this->importRecordEntry($entry['attributes'], $exportXmlPath, $run, $sourceSetId, $summary);

                    continue;
                }

                if ($entry['element'] !== 'Workout') {
                    continue;
                }

                $this->importWorkoutEntry($entry['attributes'], $exportXmlPath, $run, $sourceSetId, $summary);
            }
        });
    }

    /**
     * @param  array<string, string>  $attributes
     * @param  array{
     *     source_file:string,
     *     source_set_id:int,
     *     records:int,
     *     workouts:int,
     *     inserted_records:int,
     *     reobserved_records:int,
     *     inserted_workouts:int,
     *     reobserved_workouts:int
     * }  &$summary
     */
    private function importRecordEntry(
        array $attributes,
        string $exportXmlPath,
        Run $run,
        int $sourceSetId,
        array &$summary,
    ): void {
        $recordRow = $this->upsertRecord($attributes);

        if ($recordRow['wasRecentlyCreated']) {
            $summary['inserted_records']++;
        } else {
            $summary['reobserved_records']++;
        }

        $this->sourceObservationStore->record(
            table: 'apple_health_record_observations',
            unique: [
                'apple_health_record_id' => $recordRow['id'],
                'apple_health_source_set_id' => $sourceSetId,
            ],
        );

        $summary['records']++;

        $this->provenanceWriter->link(new WriteProvenanceLinkData(
            runId: $run->id,
            outputTarget: 'apple_health_records:'.$recordRow['id'],
            claimKey: 'imported-record',
            evidenceType: 'source-file',
            evidenceRef: basename($exportXmlPath).'#record:'.$recordRow['canonical_key'],
        ));
    }

    /**
     * @param  array<string, string>  $attributes
     * @param  array{
     *     source_file:string,
     *     source_set_id:int,
     *     records:int,
     *     workouts:int,
     *     inserted_records:int,
     *     reobserved_records:int,
     *     inserted_workouts:int,
     *     reobserved_workouts:int
     * }  &$summary
     */
    private function importWorkoutEntry(
        array $attributes,
        string $exportXmlPath,
        Run $run,
        int $sourceSetId,
        array &$summary,
    ): void {
        $workoutRow = $this->upsertWorkout($attributes);

        if ($workoutRow['wasRecentlyCreated']) {
            $summary['inserted_workouts']++;
        } else {
            $summary['reobserved_workouts']++;
        }

        $this->sourceObservationStore->record(
            table: 'apple_health_workout_observations',
            unique: [
                'apple_health_workout_id' => $workoutRow['id'],
                'apple_health_source_set_id' => $sourceSetId,
            ],
        );

        $summary['workouts']++;

        $this->provenanceWriter->link(new WriteProvenanceLinkData(
            runId: $run->id,
            outputTarget: 'apple_health_workouts:'.$workoutRow['id'],
            claimKey: 'imported-workout',
            evidenceType: 'source-file',
            evidenceRef: basename($exportXmlPath).'#workout:'.$workoutRow['canonical_key'],
        ));
    }

    private function resolveExportXmlPath(ImporterDispatchData $dispatchPayload): string
    {
        if ($dispatchPayload->accessMode === 'archive') {
            return $dispatchPayload->sourceLocator;
        }

        $exportXmlPath = rtrim($dispatchPayload->sourceLocator, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'eksport.xml';

        if (! File::exists($exportXmlPath) || ! File::isFile($exportXmlPath)) {
            throw new InvalidArgumentException(
                'Malformed Apple Health source payload: eksport.xml was not found inside the requested directory.',
            );
        }

        return $exportXmlPath;
    }

    /**
     * @return iterable<array{element:string, attributes:array<string, string>}>
     */
    private function streamEntries(string $exportXmlPath): iterable
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $reader = new XMLReader;
        $opened = $reader->open($exportXmlPath, null, LIBXML_NONET | LIBXML_PARSEHUGE);

        if ($opened === false) {
            libxml_use_internal_errors($previousUseInternalErrors);

            throw new InvalidArgumentException('Malformed Apple Health source payload: eksport.xml could not be parsed.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                if (! in_array($reader->name, ['Record', 'Workout'], true)) {
                    continue;
                }

                yield [
                    'element' => $reader->name,
                    'attributes' => $this->readAttributes($reader),
                ];
            }

            if (libxml_get_errors() !== []) {
                throw new InvalidArgumentException(
                    'Malformed Apple Health source payload: eksport.xml could not be parsed.',
                );
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    /**
     * @return array<string, string>
     */
    private function readAttributes(XMLReader $reader): array
    {
        $attributes = [];

        if (! $reader->hasAttributes) {
            return $attributes;
        }

        while ($reader->moveToNextAttribute()) {
            $attributes[$reader->name] = $reader->value;
        }

        $reader->moveToElement();

        return $attributes;
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array{id:int, canonical_key:string, wasRecentlyCreated:bool}
     */
    private function upsertRecord(array $attributes): array
    {
        $canonicalKey = sha1(json_encode([
            'record_type' => $attributes['type'] ?? null,
            'source_name' => $attributes['sourceName'] ?? null,
            'source_version' => $attributes['sourceVersion'] ?? null,
            'unit' => $attributes['unit'] ?? null,
            'value' => $attributes['value'] ?? null,
            'creation_date' => $attributes['creationDate'] ?? null,
            'start_date' => $attributes['startDate'] ?? null,
            'end_date' => $attributes['endDate'] ?? null,
        ], JSON_THROW_ON_ERROR));

        $existingId = DB::table('apple_health_records')
            ->where('canonical_key', $canonicalKey)
            ->value('id');

        $recordId = $this->sourceObservationStore->upsertAndReturnId(
            table: 'apple_health_records',
            unique: [
                'canonical_key' => $canonicalKey,
            ],
            values: [
                'record_type' => $attributes['type'] ?? null,
                'source_name' => $attributes['sourceName'] ?? null,
                'source_version' => $attributes['sourceVersion'] ?? null,
                'unit' => $attributes['unit'] ?? null,
                'value' => $attributes['value'] ?? null,
                'creation_at' => $this->normalizeTimestamp($attributes['creationDate'] ?? null),
                'start_at' => $this->normalizeTimestamp($attributes['startDate'] ?? null),
                'end_at' => $this->normalizeTimestamp($attributes['endDate'] ?? null),
                'raw_record' => json_encode($attributes, JSON_THROW_ON_ERROR),
            ],
        );

        return [
            'id' => $recordId,
            'canonical_key' => $canonicalKey,
            'wasRecentlyCreated' => $existingId === null,
        ];
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array{id:int, canonical_key:string, wasRecentlyCreated:bool}
     */
    private function upsertWorkout(array $attributes): array
    {
        $canonicalKey = sha1(json_encode([
            'workout_activity_type' => $attributes['workoutActivityType'] ?? null,
            'source_name' => $attributes['sourceName'] ?? null,
            'source_version' => $attributes['sourceVersion'] ?? null,
            'creation_date' => $attributes['creationDate'] ?? null,
            'start_date' => $attributes['startDate'] ?? null,
            'end_date' => $attributes['endDate'] ?? null,
            'duration' => $attributes['duration'] ?? null,
            'duration_unit' => $attributes['durationUnit'] ?? null,
            'total_energy_burned' => $attributes['totalEnergyBurned'] ?? null,
            'total_energy_burned_unit' => $attributes['totalEnergyBurnedUnit'] ?? null,
            'total_distance' => $attributes['totalDistance'] ?? null,
            'total_distance_unit' => $attributes['totalDistanceUnit'] ?? null,
        ], JSON_THROW_ON_ERROR));

        $existingId = DB::table('apple_health_workouts')
            ->where('canonical_key', $canonicalKey)
            ->value('id');

        $workoutId = $this->sourceObservationStore->upsertAndReturnId(
            table: 'apple_health_workouts',
            unique: [
                'canonical_key' => $canonicalKey,
            ],
            values: [
                'workout_activity_type' => $attributes['workoutActivityType'] ?? null,
                'source_name' => $attributes['sourceName'] ?? null,
                'source_version' => $attributes['sourceVersion'] ?? null,
                'duration' => $attributes['duration'] ?? null,
                'duration_unit' => $attributes['durationUnit'] ?? null,
                'total_energy_burned' => $attributes['totalEnergyBurned'] ?? null,
                'total_energy_burned_unit' => $attributes['totalEnergyBurnedUnit'] ?? null,
                'total_distance' => $attributes['totalDistance'] ?? null,
                'total_distance_unit' => $attributes['totalDistanceUnit'] ?? null,
                'creation_at' => $this->normalizeTimestamp($attributes['creationDate'] ?? null),
                'start_at' => $this->normalizeTimestamp($attributes['startDate'] ?? null),
                'end_at' => $this->normalizeTimestamp($attributes['endDate'] ?? null),
                'raw_workout' => json_encode($attributes, JSON_THROW_ON_ERROR),
            ],
        );

        return [
            'id' => $workoutId,
            'canonical_key' => $canonicalKey,
            'wasRecentlyCreated' => $existingId === null,
        ];
    }

    private function normalizeTimestamp(?string $timestamp): ?string
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        return (new DateTimeImmutable($timestamp))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function writeArtifacts(Run $run, ImporterDispatchData $dispatchPayload, array $summary): void
    {
        $this->importArtifactWriter->write(
            run: $run,
            dispatchPayload: $dispatchPayload,
            sourceType: 'apple-health',
            artifactKind: 'apple-health-import-summary',
            summary: $summary,
        );
    }
}
