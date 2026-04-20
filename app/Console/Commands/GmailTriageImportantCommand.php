<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Gmail\TriageImportantMailAction;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

class GmailTriageImportantCommand extends Command
{
    protected $signature = 'gmail:triage-important
        {--since= : Absolute or relative start date}
        {--on= : Single-day date input}
        {--window= : Relative date window like "last 7 days"}
        {--limit=25 : Maximum number of messages to inspect}
        {--query= : Additional Gmail query terms}
        {--rules= : Path to a JSON rules file}
        {--json : Emit JSON output}';

    protected $description = 'Rank Gmail messages that appear to need timely reading or response.';

    public function __construct(
        private readonly TriageImportantMailAction $triageImportantMailAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $credentialsPath = config('gmail_triage.credentials_path');

        if (! is_string($credentialsPath) || trim($credentialsPath) === '') {
            $this->error('Set NORNIR_GMAIL_CREDENTIALS to the Gmail credentials JSON path before running gmail:triage-important.');

            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if (! is_int($limit) || $limit < 1) {
            $this->error('The --limit option must be a positive integer.');

            return self::FAILURE;
        }

        try {
            $result = ($this->triageImportantMailAction)(
                credentialsPath: $credentialsPath,
                since: $this->stringOption('since'),
                on: $this->stringOption('on'),
                window: $this->stringOption('window'),
                limit: $limit,
                query: $this->stringOption('query'),
                rulesPath: $this->stringOption('rules') ?? config('gmail_triage.rules_path'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            try {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } catch (JsonException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->info('Important Gmail triage');
        $this->line('Matched: '.$result['matched_count']);

        foreach ($result['items'] as $item) {
            $this->line(sprintf(
                '[%s] %s - %s',
                $item['urgency'],
                $item['from'],
                $item['subject'],
            ));
        }

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }
}
