<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportGmailAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportGmailCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:gmail
        {source : Path to Gmail API credentials JSON}
        {--query= : Gmail query string to scope the import}
        {--validate-only : Validate credentials and scope without importing}
        {--dry-run : Process messages without writing to the database}';

    protected $description = 'Import Gmail messages into canonical MySQL tables via the Gmail API.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportGmailAction $importGmailAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();
        $query = $this->stringOption('query');

        if ($query === null || $query === '') {
            $this->error('The --query flag is required.');

            return self::FAILURE;
        }

        $this->info('Recording intake for Gmail source');

        try {
            $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
                sourceType: 'gmail',
                accessMode: 'api',
                sourceLocator: $source,
                scopeSnapshot: [
                    'query' => $query,
                ],
                importerOptions: [],
            ));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing Gmail messages');

        $importResult = ($this->importGmailAction)(
            $intakeResult->dispatchPayload,
            function (string $event, array $payload): void {
                if ($event === 'messages_fetched') {
                    $count = $payload['count'] ?? 0;
                    $this->line("Fetched {$count} messages from this page");
                }
            },
        );

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $importResult->summary,
            labels: [
                'account_email' => 'Account',
                'threads' => 'Threads',
                'messages' => 'Messages',
                'inserted_messages' => 'Inserted messages',
                'reobserved_messages' => 'Reobserved messages',
                'labels' => 'Labels synced',
                'attachments' => 'Attachment refs recorded',
            ],
        );

        return self::SUCCESS;
    }
}
