<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportWaybackAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Console\Commands\Concerns\InteractsWithImportConsole;
use App\Data\Intake\RecordIntakeData;
use App\Services\Wayback\WaybackClient;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;

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
        {--dry-run : Count matching CDX captures without writing importer state}
        {--list-snapshots : With --dry-run, print a paged list of matching CDX snapshots}
        {--all-saves : With --dry-run, include all CDX saves instead of importer-eligible 200 HTML captures}
        {--page=1 : Snapshot list page to display}
        {--per-page=25 : Snapshot rows to display per page}
        {--delay-ms=2000 : Delay between Wayback requests in milliseconds}';

    protected $description = 'Import bounded Wayback captures as biographical evidence.';

    public function __construct(
        private readonly RecordIntakeAction $recordIntakeAction,
        private readonly ImportWaybackAction $importWaybackAction,
        private readonly WaybackClient $waybackClient,
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
        $from = $this->stringOption('from');
        $to = $this->stringOption('to');

        if ((bool) $this->option('dry-run')) {
            $allSaves = (bool) $this->option('all-saves');
            $listSnapshots = (bool) $this->option('list-snapshots');

            if ($allSaves || $listSnapshots) {
                $snapshots = $this->waybackClient->cdxSnapshots(
                    scope: $scope,
                    matchMode: $match,
                    from: $from,
                    to: $to,
                    importableOnly: ! $allSaves,
                    delayMs: $delayMs,
                );

                $this->printDryRunSummary(
                    scope: $scope,
                    match: $match,
                    availableCaptures: count($snapshots),
                    limit: $limit,
                    label: $allSaves ? 'Available CDX saves' : 'Available CDX snapshots',
                );

                if ($listSnapshots) {
                    $this->printSnapshotPage($snapshots);
                }

                return self::SUCCESS;
            }

            $availableCaptures = $this->waybackClient->cdxCaptureCount(
                scope: $scope,
                matchMode: $match,
                from: $from,
                to: $to,
                delayMs: $delayMs,
            );

            $this->printDryRunSummary(
                scope: $scope,
                match: $match,
                availableCaptures: $availableCaptures,
                limit: $limit,
                label: 'Available CDX captures',
            );

            return self::SUCCESS;
        }

        $this->info("Recording intake for Wayback scope: {$scope}");

        $intakeResult = ($this->recordIntakeAction)(new RecordIntakeData(
            sourceType: 'wayback',
            accessMode: 'web-api',
            sourceLocator: $scope,
            scopeSnapshot: array_filter([
                'match_mode' => $match,
                'from' => $from,
                'to' => $to,
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

        $importResult = ($this->importWaybackAction)(
            $intakeResult->dispatchPayload,
            $this->shouldPrintProgress() ? fn (string $event, array $summary): null => $this->printProgress($event, $summary) : null,
        );

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

    private function shouldPrintProgress(): bool
    {
        return $this->getOutput()->getVerbosity() !== OutputInterface::VERBOSITY_QUIET;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function printProgress(string $event, array $summary): null
    {
        if ($event === 'wayback_captures_discovered') {
            $this->line('Wayback progress: discovered '.$this->integerSummaryValue($summary, 'cdx_captures').' CDX captures');

            return null;
        }

        $this->line(sprintf(
            'Wayback progress: %d/%d processed (%d accepted, %d rejected, %d failed, %d screenshots)',
            $this->integerSummaryValue($summary, 'captures'),
            $this->integerSummaryValue($summary, 'cdx_captures'),
            $this->integerSummaryValue($summary, 'accepted'),
            $this->integerSummaryValue($summary, 'rejected'),
            $this->integerSummaryValue($summary, 'failed'),
            $this->integerSummaryValue($summary, 'screenshots'),
        ));

        return null;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function integerSummaryValue(array $summary, string $key): int
    {
        return (int) ($summary[$key] ?? 0);
    }

    private function printDryRunSummary(
        string $scope,
        string $match,
        int $availableCaptures,
        int $limit,
        string $label,
    ): void {
        $this->info('Wayback dry run');
        $this->line("Scope: {$scope}");
        $this->line("Match mode: {$match}");
        $this->line($label.': '.$availableCaptures);
        $this->line('Would process with current limit: '.min($availableCaptures, $limit));
    }

    /**
     * @param  list<array<string, mixed>>  $snapshots
     */
    private function printSnapshotPage(array $snapshots): void
    {
        $page = max(1, (int) $this->option('page'));
        $perPage = max(1, (int) $this->option('per-page'));
        $total = count($snapshots);
        $offset = ($page - 1) * $perPage;
        $pageSnapshots = array_slice($snapshots, $offset, $perPage);
        $start = $pageSnapshots === [] ? 0 : $offset + 1;
        $end = min($total, $offset + count($pageSnapshots));

        $this->newLine();
        $this->line("Showing snapshots {$start}-{$end} of {$total}");

        foreach ($pageSnapshots as $snapshot) {
            $timestamp = $this->snapshotValue($snapshot, 'timestamp');
            $original = $this->snapshotValue($snapshot, 'original');

            $this->line($this->formatWaybackTimestamp($timestamp).'  '.$timestamp.'  '.$this->snapshotValue($snapshot, 'statuscode').'  '.$this->snapshotValue($snapshot, 'mimetype'));
            $this->line('Original: '.$original);
            $this->line('Replay: '.$this->waybackClient->replayUrl($timestamp, $original));
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotValue(array $snapshot, string $key): string
    {
        return (string) ($snapshot[$key] ?? '');
    }

    private function formatWaybackTimestamp(string $timestamp): string
    {
        $date = DateTimeImmutable::createFromFormat('YmdHis', $timestamp, new DateTimeZone('UTC'));

        if ($date === false) {
            return $timestamp;
        }

        return $date->format('Y-m-d H:i:s').' UTC';
    }
}
