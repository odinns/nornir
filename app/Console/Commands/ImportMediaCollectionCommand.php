<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportMediaCollectionAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportMediaCollectionCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:media-collection
        {--source-dsn= : Named DB connection or DSN for monique (required)}
        {--volume= : Volume label to restrict import (e.g. LIMA-2). Omit for all volumes}
        {--dry-run : Report counts without writing to media_files}';

    protected $description = 'Import photo and video records from the monique database into canonical media_files table.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportMediaCollectionAction $importMediaCollectionAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceDsn = $this->stringOption('source-dsn');

        if ($sourceDsn === null || $sourceDsn === '') {
            throw new InvalidArgumentException('--source-dsn is required.');
        }

        $volume = $this->stringOption('volume');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Recording intake for media-collection source: {$sourceDsn}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'media-collection',
            accessMode: 'db-connection',
            sourceLocator: $sourceDsn,
            scopeSnapshot: [
                'source_dsn' => $sourceDsn,
                'volume' => $volume,
            ],
            importerOptions: [
                'volume' => $volume,
                'dry_run' => $dryRun,
            ],
        ));

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing media collection');

        $importResult = ($this->importMediaCollectionAction)(
            $intakeResult->dispatchPayload,
            function (string $event, array $payload): void {
                if ($event !== 'page_imported') {
                    return;
                }

                $inspected = $payload['files_inspected'] ?? 0;
                $imported = $payload['files_imported'] ?? 0;
                $this->line("Processed {$inspected} files ({$imported} imported)");
            },
        );

        /** @var array<string, int|string> $printableSummary */
        $printableSummary = array_filter(
            $importResult->summary,
            static fn (mixed $value): bool => is_int($value) || is_string($value),
        );

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $printableSummary,
            labels: [
                'source_dsn' => 'Source DSN',
                'volume' => 'Volume filter',
                'files_inspected' => 'Files inspected',
                'files_imported' => 'Files imported',
                'files_reobserved' => 'Files reobserved',
            ],
        );

        return self::SUCCESS;
    }
}
