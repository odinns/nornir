<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportChatGptConversationsAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;

class ImportChatGptCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:chatgpt
        {source : Path to a ChatGPT export directory or conversation JSON file}
        {--archive-label= : Optional archive label}
        {--root=* : Additional allowed root paths for local-path imports}
        {--glob=conversations-*.json : Relative glob for conversation files inside a local path root}';

    protected $description = 'Import ChatGPT export conversations into canonical MySQL tables.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportChatGptConversationsAction $importChatGptConversationsAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();
        $accessMode = $this->resolveFilesystemAccessMode($source);
        $scopeSnapshot = $this->buildScopeSnapshot($source, $accessMode);

        $this->info("Recording intake for ChatGPT source: {$source}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'chatgpt',
            accessMode: $accessMode,
            sourceLocator: $source,
            scopeSnapshot: $scopeSnapshot,
            importerOptions: [],
        ));

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing ChatGPT conversations');

        $importResult = ($this->importChatGptConversationsAction)(
            $intakeResult->dispatchPayload,
            function (string $event, array $payload): void {
                if ($event === 'files_resolved') {
                    $totalFiles = $payload['total_files'] ?? 0;
                    $this->line("Found {$totalFiles} conversation files to import");

                    return;
                }

                if ($event !== 'file_completed') {
                    return;
                }

                $currentFile = $payload['current_file'] ?? '?';
                $totalFiles = $payload['total_files'] ?? '?';
                $file = $payload['file'] ?? 'unknown-file';
                $fileConversations = $payload['file_conversations'] ?? 0;
                $fileMessages = $payload['file_messages'] ?? 0;
                $conversations = $payload['conversations'] ?? 0;
                $messages = $payload['messages'] ?? 0;

                $this->line("[{$currentFile}/{$totalFiles}] {$file}");
                $this->line("Running totals: {$conversations} conversations, {$messages} messages");
                $this->line("File totals: {$fileConversations} conversations, {$fileMessages} messages");
            },
        );

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $importResult->summary,
            labels: [
                'source_file' => 'Source file',
                'conversations' => 'Imported conversations',
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
    private function buildScopeSnapshot(string $source, string $accessMode): array
    {
        if ($accessMode === 'archive') {
            $scopeSnapshot = [];
            $archiveLabel = $this->stringOption('archive-label');

            if ($archiveLabel !== null && $archiveLabel !== '') {
                $scopeSnapshot['archive_label'] = $archiveLabel;
            }

            return $scopeSnapshot;
        }

        $rootOption = $this->option('root');
        $additionalRootCandidates = is_array($rootOption) ? $rootOption : [];
        $additionalRoots = array_values(array_filter(
            $additionalRootCandidates,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        return [
            'accepted_root_paths' => array_values(array_unique([$source, ...$additionalRoots])),
            'relative_glob' => $this->stringOption('glob') ?? 'conversations-*.json',
        ];
    }
}
