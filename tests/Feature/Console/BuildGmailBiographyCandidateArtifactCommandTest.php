<?php

declare(strict_types=1);

use App\Models\Run;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    cleanupGmailManualAnalysisTestFiles();
    date_default_timezone_set('Europe/Copenhagen');
    $this->travelTo(CarbonImmutable::parse('2026-04-20 15:45:00', 'Europe/Copenhagen'));
});

afterEach(function (): void {
    cleanupGmailManualAnalysisTestFiles();
});

it('builds a gmail biography candidate artifact from the cli as json', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'items' => [
            gmailManualAnalysisBundleItem(
                messageId: 'msg-cli',
                threadId: 'thread-cli',
                subject: 'CLI message',
                receivedAt: '2026-04-20T12:30:00+00:00',
            ),
        ],
    ]);

    Artisan::call('muninn:gmail-biography-candidates', [
        '--bundle' => relativeGmailBiographyCandidatePath($bundlePath),
        '--json' => true,
    ]);

    $decoded = decodeGmailBiographyCandidateCommandOutput();

    expect(array_keys($decoded))->toBe([
        'candidate_path',
        'candidate_count',
        'source_run_id',
        'evidence_run_id',
        'candidate_run_id',
    ]);
    expect($decoded)->toMatchArray([
        'candidate_count' => 1,
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
    ]);
    expect($decoded['candidate_path'])->toBeString();
    expect($decoded['candidate_run_id'])->toBeInt();
    expect(File::exists($decoded['candidate_path']))->toBeTrue();
    expect(Run::query()
        ->whereKey($decoded['candidate_run_id'])
        ->where('operation', 'gmail-biography-candidates')
        ->exists())->toBeTrue();
});

function relativeGmailBiographyCandidatePath(string $path): string
{
    return substr($path, strlen(base_path().DIRECTORY_SEPARATOR));
}

/**
 * @return array{candidate_path:string, candidate_count:int, source_run_id:int, evidence_run_id:int, candidate_run_id:int}
 */
function decodeGmailBiographyCandidateCommandOutput(): array
{
    $decoded = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException('Command JSON did not decode to an object.');
    }

    foreach (['candidate_path', 'candidate_count', 'source_run_id', 'evidence_run_id', 'candidate_run_id'] as $key) {
        if (! array_key_exists($key, $decoded)) {
            throw new RuntimeException("Command JSON is missing [{$key}].");
        }
    }

    $candidatePath = $decoded['candidate_path'] ?? null;
    $candidateCount = $decoded['candidate_count'] ?? null;
    $sourceRunId = $decoded['source_run_id'] ?? null;
    $evidenceRunId = $decoded['evidence_run_id'] ?? null;
    $candidateRunId = $decoded['candidate_run_id'] ?? null;

    if (
        ! is_string($candidatePath)
        || ! is_int($candidateCount)
        || ! is_int($sourceRunId)
        || ! is_int($evidenceRunId)
        || ! is_int($candidateRunId)
    ) {
        throw new RuntimeException('Command JSON returned unexpected value types.');
    }

    return [
        'candidate_path' => $candidatePath,
        'candidate_count' => $candidateCount,
        'source_run_id' => $sourceRunId,
        'evidence_run_id' => $evidenceRunId,
        'candidate_run_id' => $candidateRunId,
    ];
}
