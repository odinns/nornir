<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gmail\GmailHtmlBodyTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillGmailBodyPlainCommand extends Command
{
    private const string BLANK_BODY_PLAIN_SQL = "REGEXP_REPLACE(body_plain, '[[:space:]]+', '') = ''";

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
        $wouldSkipEmpty = 0;
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

                    if ($bodyPlain === null) {
                        $wouldSkipEmpty++;

                        continue;
                    }

                    $wouldUpdate++;
                }

                $failedConversions += $batchFailedConversions;
                $processed += $rows->count();
                $lastId = (int) $rows->last()->id;

                if ($batchFailedConversions > 0) {
                    $this->error($this->failedBatchMessage($rows));
                    $this->line('Would update: '.$wouldUpdate);
                    $this->line('Would skip empty: '.$wouldSkipEmpty);
                    $this->line('Failed conversions: '.$failedConversions);

                    return self::FAILURE;
                }

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
                        if ($bodyPlain === null) {
                            continue;
                        }

                        $batchUpdated += DB::table('gmail_messages')
                            ->where('id', $id)
                            ->where(static function ($query): void {
                                $query
                                    ->whereNull('body_plain')
                                    ->orWhereRaw(self::BLANK_BODY_PLAIN_SQL);
                            })
                            ->update([
                                'body_plain' => $bodyPlain,
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
        }

        if ($isDryRun) {
            $this->line('Would update: '.$wouldUpdate);
            $this->line('Would skip empty: '.$wouldSkipEmpty);
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

    private function candidateQuery(): Builder
    {
        return DB::table('gmail_messages')
            ->where(static function ($query): void {
                $query
                    ->whereNull('body_plain')
                    ->orWhereRaw(self::BLANK_BODY_PLAIN_SQL);
            })
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
