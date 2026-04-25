<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportAppleMessagesAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;

class ImportAppleMessagesCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:apple-messages
        {source : Path to an Apple Messages chat.db file or a directory containing chat.db}
        {--attachments-root= : Optional attachments root for attachment path normalization}
        {--contacts-db=* : Optional AddressBook sqlite database paths for participant name enrichment}';

    protected $description = 'Import Apple Messages history into canonical MySQL tables.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportAppleMessagesAction $importAppleMessagesAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();
        $accessMode = $this->resolveFilesystemAccessMode($source);
        $scopeSnapshot = $this->buildScopeSnapshot($source);

        $this->info("Recording intake for Apple Messages source: {$source}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'apple-messages',
            accessMode: $accessMode,
            sourceLocator: $source,
            scopeSnapshot: $scopeSnapshot,
            importerOptions: [],
        ));

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing Apple Messages');

        $importResult = ($this->importAppleMessagesAction)(
            $intakeResult->dispatchPayload,
            function (string $event, array $payload): void {
                if ($event === 'chats_resolved') {
                    $this->line('Found '.($payload['total_chats'] ?? 0).' chats to import');

                    return;
                }

                if ($event !== 'chat_completed') {
                    return;
                }

                $currentChat = $payload['current_chat'] ?? '?';
                $totalChats = $payload['total_chats'] ?? '?';
                $chat = $payload['chat'] ?? 'unknown-chat';
                $messages = $payload['messages'] ?? 0;

                $this->line("[{$currentChat}/{$totalChats}] {$chat}");
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

    /**
     * @return array<string, mixed>
     */
    private function buildScopeSnapshot(string $source): array
    {
        $attachmentsRoot = $this->stringOption('attachments-root');
        $acceptedRootPaths = [is_file($source) ? dirname($source) : $source];
        $contactsDatabases = $this->stringOptions('contacts-db');

        if ($attachmentsRoot !== null && $attachmentsRoot !== '') {
            $acceptedRootPaths[] = $attachmentsRoot;
        }

        foreach ($contactsDatabases as $contactsDatabase) {
            $acceptedRootPaths[] = dirname($contactsDatabase);
        }

        return array_filter([
            'accepted_root_paths' => array_values(array_unique($acceptedRootPaths)),
            'attachments_root' => $attachmentsRoot,
            'contacts_databases' => $contactsDatabases,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
