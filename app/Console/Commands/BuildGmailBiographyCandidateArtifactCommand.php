<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Muninn\BuildGmailBiographyCandidateArtifactAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BuildGmailBiographyCandidateArtifactCommand extends Command
{
    protected $signature = 'muninn:gmail-biography-candidates
        {--bundle= : Gmail important evidence bundle path}
        {--output= : Optional JSON artifact path}
        {--json : Print machine-readable JSON}';

    protected $description = 'Build conservative Muninn biography candidates from a Gmail evidence bundle.';

    public function __construct(
        private readonly BuildGmailBiographyCandidateArtifactAction $buildGmailBiographyCandidateArtifactAction,
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
            $result = ($this->buildGmailBiographyCandidateArtifactAction)(
                bundlePath: $bundlePath,
                outputPath: $this->stringOption('output'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = [
            'candidate_path' => $result->candidatePath,
            'candidate_count' => $result->candidateCount,
            'source_run_id' => $result->sourceRunId,
            'evidence_run_id' => $result->evidenceRunId,
            'candidate_run_id' => $result->run->id,
        ];

        if ($this->option('json') === true) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Gmail biography candidate artifact ready');
        $this->line('Candidate path: '.$summary['candidate_path']);
        $this->line('Candidate count: '.$summary['candidate_count']);
        $this->line('Source run id: '.$summary['source_run_id']);
        $this->line('Evidence run id: '.$summary['evidence_run_id']);
        $this->line('Candidate run id: '.$summary['candidate_run_id']);

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
