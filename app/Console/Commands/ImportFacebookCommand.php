<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportFacebookArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use Illuminate\Console\Command;

class ImportFacebookCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:facebook
        {source : Path to a Facebook export archive directory}';

    protected $description = 'Import Facebook archive biography slices into canonical MySQL tables.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportFacebookArchiveAction $importFacebookArchiveAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();

        $this->info("Recording intake for Facebook source: {$source}");

        $intakeResult = $this->recordImportIntake(
            recordIntakeAction: $this->recordIntakeAction,
            sourceType: 'facebook',
            accessMode: 'local-path',
            sourceLocator: $source,
            scopeSnapshot: [
                'accepted_root_paths' => [$source],
            ],
        );

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing Facebook archive');

        $importResult = ($this->importFacebookArchiveAction)(
            $intakeResult->dispatchPayload,
            function (string $event, array $payload): void {
                if ($event === 'threads_resolved') {
                    $this->line('Found '.($payload['total_threads'] ?? 0).' threads to import');

                    return;
                }

                if ($event !== 'thread_completed') {
                    return;
                }

                $currentThread = $payload['current_thread'] ?? '?';
                $totalThreads = $payload['total_threads'] ?? '?';
                $thread = $payload['thread'] ?? 'unknown-thread';
                $messages = $payload['messages'] ?? 0;

                $this->line("[{$currentThread}/{$totalThreads}] {$thread}");
                $this->line("Running totals: {$messages} messages");
            },
        );

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $importResult->summary,
            labels: [
                'source_file' => 'Source file',
                'messages' => 'Imported messages',
                'inserted_messages' => 'Inserted messages',
                'reobserved_messages' => 'Reobserved messages',
            ],
        );

        return self::SUCCESS;
    }
}
