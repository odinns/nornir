<?php

declare(strict_types=1);

use App\Actions\Muninn\BuildGmailManualAnalysisNoteAction;
use App\Models\ProvenanceLink;
use App\Models\Run;
use App\Models\RunArtifact;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('builds a gmail manual analysis note from a valid evidence bundle', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'items' => [
            gmailManualAnalysisBundleItem(
                messageId: 'msg-new',
                threadId: 'thread-shared',
                subject: 'Newer contract question',
                receivedAt: '2026-04-20T12:30:00+00:00',
            ),
            gmailManualAnalysisBundleItem(
                messageId: 'msg-old',
                threadId: 'thread-shared',
                subject: 'Older contract question',
                receivedAt: '2026-04-18T08:30:00+00:00',
            ),
        ],
    ]);

    $result = app(BuildGmailManualAnalysisNoteAction::class)($bundlePath);

    expect(File::exists($result->notePath))->toBeTrue();
    expect($result->sourceRunId)->toBe($runs['source']->id);
    expect($result->evidenceRunId)->toBe($runs['evidence']->id);
    expect($result->itemCount)->toBe(2);

    $note = (string) File::get($result->notePath);

    expect($note)
        ->toContain('# Gmail important manual analysis note')
        ->toContain('Source run id: '.$runs['source']->id)
        ->toContain('Evidence run id: '.$runs['evidence']->id)
        ->toContain('Bundle path: '.$bundlePath)
        ->toContain('gmail_messages:msg-old')
        ->toContain('gmail_messages:msg-new')
        ->toContain('## Contradiction review')
        ->toContain('## Missing context')
        ->toContain('## Next action');
    expect(positionInManualAnalysisNote($note, 'gmail_messages:msg-old'))
        ->toBeLessThan(positionInManualAnalysisNote($note, 'gmail_messages:msg-new'));

    expect($result->run->parent_run_id)->toBe($runs['evidence']->id);
    expect($result->run->subsystem)->toBe('muninn');
    expect($result->run->operation)->toBe('manual-analysis-note');
    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);

    expect(RunArtifact::query()
        ->where('run_id', $result->run->id)
        ->where('artifact_kind', 'manual-analysis-note')
        ->where('locator', $result->notePath)
        ->where('classification', 'review')
        ->exists())->toBeTrue();

    expect(ProvenanceLink::query()
        ->where('run_id', $result->run->id)
        ->where('claim_key', 'chronology-candidate')
        ->orderBy('id')
        ->pluck('evidence_ref')
        ->all())->toBe([
            'gmail_messages:msg-old',
            'gmail_messages:msg-new',
        ]);

    expect(ProvenanceLink::query()
        ->where('run_id', $result->run->id)
        ->where('claim_key', 'thread-evidence-item')
        ->orderBy('id')
        ->pluck('evidence_ref')
        ->all())->toBe([
            'gmail_messages:msg-old',
            'gmail_messages:msg-new',
        ]);
});

it('rejects missing or unsupported schema versions', function (?int $schemaVersion): void {
    $runs = createManualAnalysisBundleRuns();
    $bundle = gmailManualAnalysisBundleDefaults();
    $bundle['source_run_id'] = $runs['source']->id;
    $bundle['evidence_run_id'] = $runs['evidence']->id;

    if ($schemaVersion === null) {
        unset($bundle['schema_version']);
    } else {
        $bundle['schema_version'] = $schemaVersion;
    }

    $bundlePath = writeRawGmailManualAnalysisBundle($bundle);

    expect(fn () => app(BuildGmailManualAnalysisNoteAction::class)($bundlePath))
        ->toThrow(InvalidArgumentException::class, 'Only evidence bundle schema_version 1 is supported.');
})->with([
    'missing' => null,
    'unsupported' => 2,
]);

it('rejects unsupported bundle types', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'bundle_type' => 'chatgpt-important-thread',
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
    ]);

    expect(fn () => app(BuildGmailManualAnalysisNoteAction::class)($bundlePath))
        ->toThrow(InvalidArgumentException::class, 'Only gmail-important-mail evidence bundles are supported.');
});

it('rejects bundles whose evidence run does not belong to the source run', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $otherSourceRun = Run::query()->create([
        'subsystem' => 'import',
        'operation' => 'gmail-import',
        'status' => Run::STATUS_SUCCEEDED,
        'input_scope' => [
            'source_locator' => base_path('data/test-fixtures/gmail/other-credentials.json'),
            'scope_snapshot' => ['query' => 'label:other'],
        ],
        'idempotency_key' => 'gmail-import:manual-analysis-other-test',
        'started_at' => now(),
        'finished_at' => now(),
    ]);
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $otherSourceRun->id,
        'evidence_run_id' => $runs['evidence']->id,
    ]);

    expect(fn () => app(BuildGmailManualAnalysisNoteAction::class)($bundlePath))
        ->toThrow(InvalidArgumentException::class, 'Evidence run referenced by bundle does not belong to the source Gmail import run.');
});

it('writes an empty but valid note when the bundle has no items', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'items' => [],
    ]);

    $result = app(BuildGmailManualAnalysisNoteAction::class)($bundlePath);
    $note = (string) File::get($result->notePath);

    expect($result->itemCount)->toBe(0);
    expect($note)
        ->toContain('## Chronology candidates')
        ->toContain('No chronology candidates in this bundle.')
        ->toContain('## Evidence by thread')
        ->toContain('No evidence items in this bundle.')
        ->toContain('- [ ] No contradiction review started.')
        ->toContain('- [ ] No missing context reviewed.')
        ->toContain('- [ ] No next action selected.');
    expect(ProvenanceLink::query()->where('run_id', $result->run->id)->count())->toBe(0);
});

it('groups messages by thread without dropping per-message refs', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'items' => [
            gmailManualAnalysisBundleItem(
                messageId: 'msg-a2',
                threadId: 'thread-a',
                subject: 'Second thread A message',
                receivedAt: '2026-04-20T12:30:00+00:00',
            ),
            gmailManualAnalysisBundleItem(
                messageId: 'msg-b1',
                threadId: 'thread-b',
                subject: 'Thread B message',
                receivedAt: '2026-04-19T09:00:00+00:00',
            ),
            gmailManualAnalysisBundleItem(
                messageId: 'msg-a1',
                threadId: 'thread-a',
                subject: 'First thread A message',
                receivedAt: '2026-04-18T08:30:00+00:00',
            ),
        ],
    ]);

    $result = app(BuildGmailManualAnalysisNoteAction::class)($bundlePath);
    $note = (string) File::get($result->notePath);

    $threadSection = substr($note, positionInManualAnalysisNote($note, '## Evidence by thread'));

    expect($threadSection)
        ->toContain('### thread-a')
        ->toContain('`gmail_messages:msg-a1`')
        ->toContain('`gmail_messages:msg-a2`')
        ->toContain('### thread-b')
        ->toContain('`gmail_messages:msg-b1`');
    expect(substr_count($threadSection, 'gmail_messages:msg-a1'))->toBeGreaterThanOrEqual(1);
    expect(substr_count($threadSection, 'gmail_messages:msg-a2'))->toBeGreaterThanOrEqual(1);
    expect(substr_count($threadSection, 'gmail_messages:msg-b1'))->toBeGreaterThanOrEqual(1);

    $threadTargets = ProvenanceLink::query()
        ->where('run_id', $result->run->id)
        ->where('claim_key', 'thread-evidence-item')
        ->pluck('output_target', 'evidence_ref')
        ->all();
    /** @var array<string, string> $threadTargets */
    expect(manualAnalysisThreadTarget($threadTargets, 'gmail_messages:msg-a1'))->toContain('#evidence-by-thread.gmail_messages:msg-a1');
    expect(manualAnalysisThreadTarget($threadTargets, 'gmail_messages:msg-a2'))->toContain('#evidence-by-thread.gmail_messages:msg-a2');
    expect(manualAnalysisThreadTarget($threadTargets, 'gmail_messages:msg-b1'))->toContain('#evidence-by-thread.gmail_messages:msg-b1');
});

it('orders chronology by parsed received_at timestamps', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'items' => [
            gmailManualAnalysisBundleItem(
                messageId: 'msg-utc',
                threadId: 'thread-utc',
                subject: 'UTC message',
                receivedAt: '2026-04-20T09:30:00+00:00',
            ),
            gmailManualAnalysisBundleItem(
                messageId: 'msg-offset',
                threadId: 'thread-offset',
                subject: 'Offset message',
                receivedAt: '2026-04-20T11:00:00+02:00',
            ),
        ],
    ]);

    $result = app(BuildGmailManualAnalysisNoteAction::class)($bundlePath);
    $note = (string) File::get($result->notePath);

    expect(positionInManualAnalysisNote($note, 'gmail_messages:msg-offset'))
        ->toBeLessThan(positionInManualAnalysisNote($note, 'gmail_messages:msg-utc'));
});

function positionInManualAnalysisNote(string $note, string $needle): int
{
    $position = strpos($note, $needle);

    if ($position === false) {
        throw new RuntimeException("Expected note to contain [{$needle}].");
    }

    return $position;
}

/**
 * @param  array<string, string>  $targets
 */
function manualAnalysisThreadTarget(array $targets, string $evidenceRef): string
{
    $target = $targets[$evidenceRef] ?? null;

    if ($target === null) {
        throw new RuntimeException("Expected thread target for [{$evidenceRef}].");
    }

    return $target;
}
