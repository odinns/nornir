<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportTwitterArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;

class ImportTwitterCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:twitter
        {source : Path to a Twitter export archive directory}';

    protected $description = 'Import Twitter archive biography slices into canonical MySQL tables.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportTwitterArchiveAction $importTwitterArchiveAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();

        $this->info("Recording intake for Twitter source: {$source}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'twitter',
            accessMode: 'local-path',
            sourceLocator: $source,
            scopeSnapshot: [
                'accepted_root_paths' => [$source],
            ],
            importerOptions: [],
        ));

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing Twitter archive');

        $importResult = ($this->importTwitterArchiveAction)($intakeResult->dispatchPayload);

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $importResult->summary,
            labels: [
                'accounts' => 'Imported accounts',
                'tweets' => 'Imported tweets',
                'note_tweets' => 'Imported note tweets',
                'media_refs' => 'Imported media refs',
                'inserted_tweets' => 'Inserted tweets',
                'reobserved_tweets' => 'Reobserved tweets',
            ],
        );

        return self::SUCCESS;
    }
}
