<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportFidonetSourceAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;

class ImportFidonetCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:fidonet
        {source : Path to the GoldED app .env file}
        {--area=* : Optional area codes to include}
        {--exclude-area=* : Optional area codes to exclude}';

    protected $description = 'Import FidoNet biography-and-timeline threads from the canonical GoldED database.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportFidonetSourceAction $importFidonetSourceAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();
        $includeAreas = $this->stringOptions('area');
        $excludeAreas = $this->stringOptions('exclude-area');

        $this->info("Recording intake for FidoNet source: {$source}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'fidonet',
            accessMode: 'database',
            sourceLocator: $source,
            scopeSnapshot: array_filter([
                'selection_mode' => 'odinn-thread-scope',
                'area_include_codes' => $includeAreas === [] ? null : $includeAreas,
                'area_exclude_codes' => $excludeAreas === [] ? null : $excludeAreas,
            ], static fn (mixed $value): bool => $value !== null),
            importerOptions: [],
        ));

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing FidoNet source');

        $importResult = ($this->importFidonetSourceAction)($intakeResult->dispatchPayload);

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $importResult->summary,
            labels: [
                'areas' => 'Imported areas',
                'threads' => 'Imported threads',
                'messages' => 'Imported messages',
                'participants' => 'Derived participants',
                'cleanup_rows' => 'Derived cleanup rows',
                'test_like_messages' => 'Test-like messages',
            ],
        );

        return self::SUCCESS;
    }
}
