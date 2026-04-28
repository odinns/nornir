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

it('builds a manual analysis note from the cli as json', function (): void {
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

    Artisan::call('analysis:manual-evidence', [
        '--bundle' => relativeManualAnalysisPath($bundlePath),
        '--json' => true,
    ]);

    $decoded = decodeManualAnalysisCommandOutput();

    expect(array_keys($decoded))->toBe([
        'note_path',
        'source_run_id',
        'evidence_run_id',
        'manual_analysis_run_id',
        'item_count',
    ]);
    expect($decoded)->toMatchArray([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'item_count' => 1,
    ]);
    expect($decoded['note_path'])->toBeString();
    expect($decoded['manual_analysis_run_id'])->toBeInt();
    expect(File::exists($decoded['note_path']))->toBeTrue();
    expect(Run::query()
        ->whereKey($decoded['manual_analysis_run_id'])
        ->where('operation', 'manual-analysis-note')
        ->exists())->toBeTrue();
});

it('writes the note to the requested output path', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
    ]);
    $outputPath = 'data/reviews/operator-notes/custom-manual-note.md';

    Artisan::call('analysis:manual-evidence', [
        '--bundle' => relativeManualAnalysisPath($bundlePath),
        '--output' => $outputPath,
        '--json' => true,
    ]);

    $decoded = decodeManualAnalysisCommandOutput();

    expect($decoded['note_path'])->toBe(base_path($outputPath));
    expect(File::exists(base_path($outputPath)))->toBeTrue();
});

function relativeManualAnalysisPath(string $path): string
{
    return substr($path, strlen(base_path().DIRECTORY_SEPARATOR));
}

/**
 * @return array{note_path:string, source_run_id:int, evidence_run_id:int, manual_analysis_run_id:int, item_count:int}
 */
function decodeManualAnalysisCommandOutput(): array
{
    $decoded = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException('Command JSON did not decode to an object.');
    }

    foreach (['note_path', 'source_run_id', 'evidence_run_id', 'manual_analysis_run_id', 'item_count'] as $key) {
        if (! array_key_exists($key, $decoded)) {
            throw new RuntimeException("Command JSON is missing [{$key}].");
        }
    }

    $notePath = $decoded['note_path'] ?? null;
    $sourceRunId = $decoded['source_run_id'] ?? null;
    $evidenceRunId = $decoded['evidence_run_id'] ?? null;
    $manualAnalysisRunId = $decoded['manual_analysis_run_id'] ?? null;
    $itemCount = $decoded['item_count'] ?? null;

    if (
        ! is_string($notePath)
        || ! is_int($sourceRunId)
        || ! is_int($evidenceRunId)
        || ! is_int($manualAnalysisRunId)
        || ! is_int($itemCount)
    ) {
        throw new RuntimeException('Command JSON returned unexpected value types.');
    }

    return [
        'note_path' => $notePath,
        'source_run_id' => $sourceRunId,
        'evidence_run_id' => $evidenceRunId,
        'manual_analysis_run_id' => $manualAnalysisRunId,
        'item_count' => $itemCount,
    ];
}
