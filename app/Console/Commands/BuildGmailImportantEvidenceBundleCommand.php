<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Gmail\BuildGmailImportantEvidenceBundleAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BuildGmailImportantEvidenceBundleCommand extends Command
{
    protected $signature = 'evidence:gmail-important
        {--run-id= : Successful Gmail import run id to build from}
        {--limit=50 : Maximum number of important messages to include}
        {--rules= : Optional JSON priority rules path}
        {--json : Print machine-readable JSON}';

    protected $description = 'Build a reviewable important-mail evidence bundle from imported Gmail rows.';

    public function __construct(
        private readonly BuildGmailImportantEvidenceBundleAction $buildGmailImportantEvidenceBundleAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = $this->positiveIntegerOption('run-id');

        if ($runId === null) {
            $this->error('The --run-id option is required and must be a positive integer.');

            return self::FAILURE;
        }

        $limit = $this->positiveIntegerOption('limit');

        if ($limit === null) {
            $this->error('The --limit option must be a positive integer.');

            return self::FAILURE;
        }

        $rulesPath = $this->option('rules');
        $rulesPath = is_string($rulesPath) && trim($rulesPath) !== '' ? trim($rulesPath) : null;

        try {
            $result = ($this->buildGmailImportantEvidenceBundleAction)(
                runId: $runId,
                limit: $limit,
                rulesPath: $rulesPath,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = [
            'bundle_path' => $result->path,
            'matched_count' => $result->matchedCount,
            'source_run_id' => $result->sourceRunId,
            'evidence_run_id' => $result->run->id,
            'source_set_ids' => $result->sourceSetIds,
        ];

        if ($this->option('json') === true) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Gmail important evidence bundle ready');
        $this->line('Bundle path: '.$summary['bundle_path']);
        $this->line('Matched count: '.$summary['matched_count']);
        $this->line('Source import run id: '.$summary['source_run_id']);
        $this->line('Evidence run id: '.$summary['evidence_run_id']);

        return self::SUCCESS;
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if (is_int($value) && $value >= 1) {
            return $value;
        }

        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }
}
