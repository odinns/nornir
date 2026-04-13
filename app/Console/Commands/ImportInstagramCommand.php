<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportInstagramArchiveAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;

class ImportInstagramCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:instagram
        {source : Path to an Instagram export archive directory}';

    protected $description = 'Import Instagram archive account, posts, and media refs into canonical MySQL tables.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportInstagramArchiveAction $importInstagramArchiveAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->sourceArgument();

        $this->info("Recording intake for Instagram source: {$source}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'instagram',
            accessMode: 'local-path',
            sourceLocator: $source,
            scopeSnapshot: ['archive_root' => $source],
            importerOptions: [],
        ));

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing Instagram archive');

        $importResult = ($this->importInstagramArchiveAction)(
            $intakeResult->dispatchPayload,
            function (string $event, array $payload): void {
                if ($event !== 'posts_imported') {
                    return;
                }

                $posts = $payload['posts'] ?? 0;
                $this->line("Imported {$posts} posts");
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
                'username' => 'Account',
                'posts' => 'Posts',
                'inserted_posts' => 'Inserted posts',
                'reobserved_posts' => 'Reobserved posts',
                'media_refs' => 'Media refs',
                'profile_photos' => 'Profile photos',
                'stories' => 'Stories',
            ],
        );

        return self::SUCCESS;
    }
}
