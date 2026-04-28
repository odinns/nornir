<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GmailMessage;
use App\Services\Gmail\GmailHtmlBodyTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillGmailBodyPlainCommand extends Command
{
    private const string NON_BLANK_BODY_HTML_SQL = "REGEXP_REPLACE(body_html, '[[:space:]]+', '') <> ''";

    protected $signature = 'gmail:backfill-body-plain
        {--dry-run : Report candidate rows without writing changes}
        {--chunk=500 : Number of candidate rows to process per batch}
        {--limit= : Maximum number of candidate rows to inspect}';

    protected $description = 'Backfill Gmail plain text bodies from HTML bodies for search indexing.';

    public function __construct(
        private readonly GmailHtmlBodyTextExtractor $htmlBodyTextExtractor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunkSize = $this->positiveIntegerOption('chunk');
        $limit = $this->nullablePositiveIntegerOption('limit');

        if ($chunkSize === null || $limit === false) {
            return self::FAILURE;
        }

        $candidateCount = $limit === null
            ? $this->candidateQuery()->count()
            : min($this->candidateQuery()->count(), $limit);

        $this->line('Candidates: '.$candidateCount);

        $updated = 0;
        $wouldUpdate = 0;
        $wouldWriteEmpty = 0;
        $failedConversions = 0;
        $processed = 0;
        $lastId = 0;
        $isDryRun = (bool) $this->option('dry-run');

        while ($limit === null || $processed < $limit) {
            $remaining = $limit === null ? $chunkSize : min($chunkSize, $limit - $processed);

            if ($remaining < 1) {
                break;
            }

            $rows = $this->candidateQuery()
                ->select(['id', 'body_html'])
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            if ($isDryRun) {
                $batchFailedConversions = 0;

                foreach ($rows as $row) {
                    try {
                        $bodyPlain = $this->htmlBodyTextExtractor->extract($row->body_html);
                    } catch (Throwable) {
                        $batchFailedConversions++;

                        continue;
                    }

                    $bodyPlain === null ? $wouldWriteEmpty++ : $wouldUpdate++;
                }

                $failedConversions += $batchFailedConversions;
                $processed += $rows->count();
                $lastId = (int) $rows->last()->id;

                if ($batchFailedConversions > 0) {
                    $this->error($this->failedBatchMessage($rows));
                    $this->line('Would update: '.$wouldUpdate);
                    $this->line('Would write empty: '.$wouldWriteEmpty);
                    $this->line('Failed conversions: '.$failedConversions);

                    return self::FAILURE;
                }

                $this->line(sprintf(
                    'Processed: %d/%d; would update: %d; would write empty: %d; failed conversions: %d.',
                    $processed,
                    $candidateCount,
                    $wouldUpdate,
                    $wouldWriteEmpty,
                    $failedConversions,
                ));

                continue;
            }

            try {
                $rendered = [];

                foreach ($rows as $row) {
                    $rendered[(int) $row->id] = $this->htmlBodyTextExtractor->extract($row->body_html);
                }

                $updated += DB::transaction(function () use ($rendered): int {
                    $batchUpdated = 0;

                    foreach ($rendered as $id => $bodyPlain) {
                        $batchUpdated += GmailMessage::query()
                            ->whereKey($id)
                            ->whereNull('body_plain')
                            ->update([
                                'body_plain' => $bodyPlain ?? '',
                                'updated_at' => now(),
                            ]);
                    }

                    return $batchUpdated;
                });
            } catch (Throwable $exception) {
                $this->error($this->failedBatchMessage($rows, $exception->getMessage()));

                return self::FAILURE;
            }

            $processed += $rows->count();
            $lastId = (int) $rows->last()->id;

            $this->line(sprintf(
                'Processed: %d/%d; updated: %d.',
                $processed,
                $candidateCount,
                $updated,
            ));
        }

        if ($isDryRun) {
            $this->line('Would update: '.$wouldUpdate);
            $this->line('Would write empty: '.$wouldWriteEmpty);
            $this->line('Failed conversions: '.$failedConversions);
            $this->line('Dry run: rendered candidates but wrote no rows.');

            return self::SUCCESS;
        }

        $this->line('Updated: '.$updated);

        return self::SUCCESS;
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if (! is_int($integer) || $integer < 1) {
            $this->error("The --{$name} option must be a positive integer.");

            return null;
        }

        return $integer;
    }

    private function nullablePositiveIntegerOption(string $name): int|false|null
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if (! is_int($integer) || $integer < 1) {
            $this->error("The --{$name} option must be a positive integer.");

            return false;
        }

        return $integer;
    }

    /**
     * @return Builder<GmailMessage>
     */
    private function candidateQuery(): Builder
    {
        return GmailMessage::query()
            ->whereNull('body_plain')
            ->whereNotNull('body_html')
            ->whereRaw(self::NON_BLANK_BODY_HTML_SQL);
    }

    private function failedBatchMessage(mixed $rows, ?string $reason = null): string
    {
        $message = sprintf(
            'Failed batch: ids %d-%d, rows %d.',
            (int) $rows->first()->id,
            (int) $rows->last()->id,
            $rows->count(),
        );

        return $reason === null ? $message : $message.' '.$reason;
    }
}
