<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportChatGptConversationsAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ImportChatGptCommand extends Command
{
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
        $accessMode = $this->resolveAccessMode($source);
        $scopeSnapshot = $this->buildScopeSnapshot($source, $accessMode);

        $this->info("Recording intake for ChatGPT source: {$source}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'chatgpt',
            accessMode: $accessMode,
            sourceLocator: $source,
            scopeSnapshot: $scopeSnapshot,
            importerOptions: [],
        ));

        $this->line("Intake record: {$intakeResult->intakeRecord->id}");
        $this->line("Review manifest: {$intakeResult->reviewManifestPath}");
        $this->info('Importing ChatGPT conversations');

        $importResult = ($this->importChatGptConversationsAction)($intakeResult->dispatchPayload);

        $this->info('Import complete');
        $this->line("Run id: {$importResult->run->id}");
        $this->line("Run status: {$importResult->run->status}");
        $this->line("Source file: {$importResult->summary['source_file']}");
        $this->line("Imported conversations: {$importResult->summary['conversations']}");
        $this->line("Imported messages: {$importResult->summary['messages']}");

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

    private function resolveAccessMode(string $source): string
    {
        if (File::isDirectory($source)) {
            return 'local-path';
        }

        if (File::isFile($source)) {
            return 'archive';
        }

        throw new InvalidArgumentException('Source locator is not reachable.');
    }

    private function sourceArgument(): string
    {
        $source = $this->argument('source');

        if (! is_string($source) || $source === '') {
            throw new InvalidArgumentException('Source locator is not reachable.');
        }

        return $source;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }
}
