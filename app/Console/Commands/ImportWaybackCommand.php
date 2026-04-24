<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportWaybackAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportWaybackCommand extends Command
{
    use InteractsWithImportConsole;

    protected $signature = 'import:wayback
        {scope : Host or URL boundary to query in the Wayback CDX API}
        {--match=host : Match mode: host, prefix, or exact}
        {--from= : Optional CDX from timestamp/date}
        {--to= : Optional CDX to timestamp/date}
        {--limit=100 : Maximum CDX captures to process}
        {--with-screenshots : Capture PNG screenshots of replay pages}
        {--mirror-assets : Mirror replay pages and assets with wget}
        {--delay-ms=2000 : Delay between Wayback requests in milliseconds}';

    protected $description = 'Import bounded Wayback captures as biographical evidence.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportWaybackAction $importWaybackAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $scope = $this->scopeArgument();
        $match = $this->stringOption('match') ?? 'host';

        if (! in_array($match, ['host', 'prefix', 'exact'], true)) {
            throw new InvalidArgumentException('Wayback match must be one of: host, prefix, exact.');
        }

        $limit = max(1, (int) $this->option('limit'));
        $delayMs = max(0, (int) $this->option('delay-ms'));

        $this->info("Recording intake for Wayback scope: {$scope}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'wayback',
            accessMode: 'web-api',
            sourceLocator: $scope,
            scopeSnapshot: array_filter([
                'match_mode' => $match,
                'from' => $this->stringOption('from'),
                'to' => $this->stringOption('to'),
                'limit' => $limit,
            ], static fn (mixed $value): bool => $value !== null),
            importerOptions: [
                'with_screenshots' => (bool) $this->option('with-screenshots'),
                'mirror_assets' => (bool) $this->option('mirror-assets'),
                'delay_ms' => $delayMs,
            ],
        ));

        $this->printIntakeSummary($intakeResult->intakeRecord->id, $intakeResult->reviewManifestPath);
        $this->info('Importing Wayback captures');

        $importResult = ($this->importWaybackAction)($intakeResult->dispatchPayload);

        $this->printImportCompletion(
            runId: $importResult->run->id,
            runStatus: $importResult->run->status,
            summary: $importResult->summary,
            labels: [
                'cdx_captures' => 'CDX captures',
                'captures' => 'Processed captures',
                'accepted' => 'Accepted captures',
                'rejected' => 'Rejected captures',
                'failed' => 'Failed captures',
                'screenshots' => 'Screenshots hydrated',
                'mirrors' => 'Mirrors hydrated',
            ],
        );

        return self::SUCCESS;
    }

    private function scopeArgument(): string
    {
        $scope = $this->argument('scope');

        if (! is_string($scope) || $scope === '') {
            throw new InvalidArgumentException('Wayback scope is required.');
        }

        return $scope;
    }
}
