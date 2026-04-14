<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportLinkedInArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use Illuminate\Console\Command;

class ImportLinkedInCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:linkedin
        {source : Path to a LinkedIn export archive directory}';

    protected $description = 'Import LinkedIn archive biography slices into canonical MySQL tables.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportLinkedInArchiveAction $importLinkedInArchiveAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();

        $this->info("Recording intake for LinkedIn source: {$source}");

        $intakeResult = $this->recordImportIntake(
            recordIntakeAction: $this->recordIntakeAction,
            sourceType: 'linkedin',
            accessMode: 'local-path',
            sourceLocator: $source,
            scopeSnapshot: [
                'accepted_root_paths' => [$source],
            ],
        );

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing LinkedIn archive');

        $importResult = ($this->importLinkedInArchiveAction)($intakeResult->dispatchPayload);

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $importResult->summary,
            labels: [
                'profile_snapshots' => 'Imported profile snapshots',
                'positions' => 'Imported positions',
                'endorsements' => 'Imported endorsements',
                'messages' => 'Imported messages',
                'inserted_messages' => 'Inserted messages',
                'reobserved_messages' => 'Reobserved messages',
            ],
        );

        return self::SUCCESS;
    }
}
