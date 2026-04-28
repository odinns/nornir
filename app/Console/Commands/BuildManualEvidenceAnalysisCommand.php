<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Muninn\BuildGmailManualAnalysisNoteAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BuildManualEvidenceAnalysisCommand extends Command
{
    protected $signature = 'analysis:manual-evidence
        {--bundle= : Gmail important evidence bundle path}
        {--output= : Optional Markdown note path}
        {--json : Print machine-readable JSON}';

    protected $description = 'Build a manual Markdown analysis note from a Gmail evidence bundle.';

    public function __construct(
        private readonly BuildGmailManualAnalysisNoteAction $buildGmailManualAnalysisNoteAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $bundlePath = $this->stringOption('bundle');

        if ($bundlePath === null) {
            $this->error('The --bundle option is required.');

            return self::FAILURE;
        }

        try {
            $result = ($this->buildGmailManualAnalysisNoteAction)(
                bundlePath: $bundlePath,
                outputPath: $this->stringOption('output'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = [
            'note_path' => $result->notePath,
            'source_run_id' => $result->sourceRunId,
            'evidence_run_id' => $result->evidenceRunId,
            'manual_analysis_run_id' => $result->run->id,
            'item_count' => $result->itemCount,
        ];

        if ($this->option('json') === true) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Gmail manual analysis note ready');
        $this->line('Note path: '.$summary['note_path']);
        $this->line('Source run id: '.$summary['source_run_id']);
        $this->line('Evidence run id: '.$summary['evidence_run_id']);
        $this->line('Manual analysis run id: '.$summary['manual_analysis_run_id']);
        $this->line('Item count: '.$summary['item_count']);

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
