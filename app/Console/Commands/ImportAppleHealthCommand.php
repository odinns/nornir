<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportAppleHealthAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;

class ImportAppleHealthCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:apple-health
        {source : Path to an Apple Health eksport.xml file or a directory containing it}';

    protected $description = 'Import Apple Health export data into canonical MySQL tables.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportAppleHealthAction $importAppleHealthAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();
        $accessMode = $this->resolveFilesystemAccessMode($source);
        $scopeSnapshot = [
            'accepted_root_paths' => [is_file($source) ? dirname($source) : $source],
        ];

        $this->info("Recording intake for Apple Health source: {$source}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'apple-health',
            accessMode: $accessMode,
            sourceLocator: $source,
            scopeSnapshot: $scopeSnapshot,
            importerOptions: [],
        ));

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing Apple Health records');

        $importResult = ($this->importAppleHealthAction)($intakeResult->dispatchPayload);

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $importResult->summary,
            labels: [
                'source_file' => 'Source file',
                'records' => 'Imported records',
                'workouts' => 'Imported workouts',
                'inserted_records' => 'Inserted records',
                'reobserved_records' => 'Reobserved records',
                'inserted_workouts' => 'Inserted workouts',
                'reobserved_workouts' => 'Reobserved workouts',
            ],
        );

        return self::SUCCESS;
    }
}
