<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\MoniqueSourceConnectionResolver;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\MediaCollectionImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportMediaCollectionAction
{
    private const string TABLE = 'media_files';

    private const int PAGE_SIZE = 500;

    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly MoniqueSourceConnectionResolver $moniqueSourceConnectionResolver,
        private readonly SourceObservationStore $sourceObservationStore,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): MediaCollectionImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'media-collection-import',
            import: fn (Run $run): array => $this->import($dispatchPayload, $run, $progress),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->importArtifactWriter->write($run, $dispatchPayload, 'media-collection', 'media-collection-import-summary', $summary);
            },
        );

        /** @var array{source_dsn:string,volume:string|null,path_prefix:string|null,files_inspected:int,files_imported:int,files_reobserved:int,volumes:list<string>} $summary */
        $summary = $execution['summary'];

        return new MediaCollectionImportResultData(
            run: $execution['run'],
            summary: $summary,
        );
    }

    /**
     * @return array{source_dsn:string,volume:string|null,path_prefix:string|null,files_inspected:int,files_imported:int,files_reobserved:int,volumes:list<string>}
     */
    private function import(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        $sourceDsn = $dispatchPayload->sourceLocator;
        $options = $dispatchPayload->importerOptions;
        $volume = is_string($options['volume'] ?? null) && $options['volume'] !== '' ? $options['volume'] : null;
        $pathPrefix = is_string($options['path_prefix'] ?? null) && $options['path_prefix'] !== ''
            ? rtrim($options['path_prefix'], '/')
            : null;
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $monique = $this->resolveMoniqueConnection($sourceDsn);
        $timestampColumns = $this->resolveTimestampColumns($monique);

        $counts = [
            'files_inspected' => 0,
            'files_imported' => 0,
            'files_reobserved' => 0,
        ];
        /** @var array<string, true> $volumesSeen */
        $volumesSeen = [];
        $offset = 0;

        while (true) {
            $query = $monique->table('files as f')
                ->join('directories as d', 'f.directory_id', '=', 'd.id')
                ->join('volumes as v', 'd.volume_id', '=', 'v.id')
                ->whereIn('f.normalized_file_type', ['image', 'video'])
                ->where('f.basename', 'not like', '._%')
                ->select([
                    'f.id as source_file_id',
                    'v.label as volume_label',
                    'v.mount_path_last_seen as volume_mount_path',
                    'd.full_path as directory_full_path',
                    'f.basename',
                    'f.extension',
                    'f.normalized_file_type',
                    'f.size_bytes',
                    $timestampColumns['created'].' as fs_created_at',
                    $timestampColumns['modified'].' as fs_modified_at',
                    'f.duplicate_key',
                ])
                ->orderBy('f.id')
                ->limit(self::PAGE_SIZE)
                ->offset($offset);

            if ($pathPrefix !== null) {
                $query->where('d.full_path', 'like', $pathPrefix.'/%');
            } else {
                $query->where('d.full_path', 'like', '/Volumes/%/Pictures/%');
            }

            if ($volume !== null) {
                $query->where('v.label', $volume);
            }

            $rows = $query->get();

            if ($rows->isEmpty()) {
                break;
            }

            // Pre-fetch existing IDs for the whole page in one query.
            $pageSourceIds = $rows->pluck('source_file_id')->map(static fn (mixed $v): int => (int) $v)->all();

            /** @var array<int, int> $existingIds keyed by source_file_id → media_files.id */
            $existingIds = DB::table(self::TABLE)
                ->whereIn('source_file_id', $pageSourceIds)
                ->pluck('id', 'source_file_id')
                ->map(static fn (mixed $v): int => (int) $v)
                ->all();

            foreach ($rows as $row) {
                $counts['files_inspected']++;

                $eventLabel = $this->extractEventLabel($row->directory_full_path);
                $eventDate = $eventLabel !== null ? $this->parseEventDate($eventLabel) : null;

                if (! $dryRun) {
                    $sourceFileId = (int) $row->source_file_id;

                    $existingId = $existingIds[$sourceFileId] ?? null;

                    $id = $this->sourceObservationStore->upsertAndReturnId(
                        table: self::TABLE,
                        unique: ['source_file_id' => $sourceFileId],
                        values: [
                            'volume_label' => $row->volume_label,
                            'volume_mount_path' => $row->volume_mount_path,
                            'directory_full_path' => $row->directory_full_path,
                            'event_label' => $eventLabel,
                            'event_date' => $eventDate,
                            'basename' => $row->basename,
                            'extension' => $row->extension,
                            'normalized_file_type' => $row->normalized_file_type,
                            'size_bytes' => $row->size_bytes,
                            'fs_created_at' => $row->fs_created_at,
                            'fs_modified_at' => $row->fs_modified_at,
                            'duplicate_key' => $row->duplicate_key,
                        ],
                    );

                    $this->provenanceWriter->link(new WriteProvenanceLinkData(
                        runId: $run->id,
                        outputTarget: self::TABLE.':'.$id,
                        claimKey: 'imported-media-file',
                        evidenceType: 'db-row',
                        evidenceRef: 'monique#files:'.$sourceFileId,
                    ));

                    if ($existingId !== null) {
                        $counts['files_reobserved']++;
                    } else {
                        $counts['files_imported']++;
                    }
                }

                $volumesSeen[$row->volume_label] = true;
            }

            if ($progress !== null) {
                $progress('page_imported', $counts);
            }

            $offset += self::PAGE_SIZE;

            if ($rows->count() < self::PAGE_SIZE) {
                break;
            }
        }

        return [
            'source_dsn' => $dispatchPayload->sourceLocator,
            'volume' => $volume,
            'path_prefix' => $pathPrefix,
            'files_inspected' => $counts['files_inspected'],
            'files_imported' => $counts['files_imported'],
            'files_reobserved' => $counts['files_reobserved'],
            'volumes' => array_keys($volumesSeen),
        ];
    }

    private function resolveMoniqueConnection(string $sourceDsn): ConnectionInterface
    {
        if (is_file($sourceDsn)) {
            return $this->moniqueSourceConnectionResolver->connect($sourceDsn);
        }

        $connections = config('database.connections');

        if (is_array($connections) && array_key_exists($sourceDsn, $connections)) {
            return DB::connection($sourceDsn);
        }

        throw new InvalidArgumentException(
            "Monique source [{$sourceDsn}] is not reachable. "
            .'Pass a source env file path or a named connection from config/database.php.',
        );
    }

    /**
     * @return array{created:string, modified:string}
     */
    private function resolveTimestampColumns(ConnectionInterface $monique): array
    {
        if (! $monique instanceof Connection) {
            throw new InvalidArgumentException('Monique source connection must expose schema metadata.');
        }

        $columns = array_fill_keys($monique->getSchemaBuilder()->getColumnListing('files'), true);

        $created = isset($columns['fs_created_at']) ? 'f.fs_created_at' : 'f.created_at_fs';
        $modified = isset($columns['fs_modified_at']) ? 'f.fs_modified_at' : 'f.modified_at_fs';

        return [
            'created' => $created,
            'modified' => $modified,
        ];
    }

    private function extractEventLabel(string $directoryFullPath): ?string
    {
        $parts = array_values(array_filter(explode('/', $directoryFullPath)));
        $picturesIndex = array_find_key($parts, fn ($part): bool => $part === 'Pictures');

        if ($picturesIndex === null) {
            return null;
        }

        // Event dir is 2 levels below Pictures: Pictures/{year}/{event}
        $eventIndex = $picturesIndex + 2;

        if (isset($parts[$eventIndex])) {
            return $parts[$eventIndex];
        }

        // Files directly in the year dir — use the year dir name
        $yearIndex = $picturesIndex + 1;

        return $parts[$yearIndex] ?? null;
    }

    private function parseEventDate(string $eventLabel): ?string
    {
        // Full date: YYYY-MM-DD
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $eventLabel, $m)) {
            return $m[1];
        }

        // Year-month only: YYYY-MM (not followed by another digit)
        if (preg_match('/^(\d{4}-\d{2})(?!\d)/', $eventLabel, $m)) {
            return $m[1].'-01';
        }

        // Year only: YYYY (not followed by another digit)
        if (preg_match('/^(\d{4})(?!\d)/', $eventLabel, $m)) {
            return $m[1].'-01-01';
        }

        return null;
    }
}
