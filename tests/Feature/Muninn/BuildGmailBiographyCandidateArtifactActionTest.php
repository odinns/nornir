<?php

declare(strict_types=1);

use App\Actions\Muninn\BuildGmailBiographyCandidateArtifactAction;
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

it('builds a gmail biography candidate artifact from a valid evidence bundle', function (): void {
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

    $result = app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath);

    expect(File::exists($result->candidatePath))->toBeTrue();
    expect($result->sourceRunId)->toBe($runs['source']->id);
    expect($result->evidenceRunId)->toBe($runs['evidence']->id);
    expect($result->candidateCount)->toBe(2);
    expect($result->run->parent_run_id)->toBe($runs['evidence']->id);
    expect($result->run->subsystem)->toBe('muninn');
    expect($result->run->operation)->toBe('gmail-biography-candidates');
    expect($result->run->status)->toBe(Run::STATUS_SUCCEEDED);

    $artifact = decodeGmailBiographyCandidateArtifact($result->candidatePath);

    expect($artifact)->toMatchArray([
        'schema_version' => 1,
        'artifact_type' => 'muninn-biography-candidates',
        'source_bundle_path' => $bundlePath,
        'source_bundle_type' => 'gmail-important-mail',
        'source_type' => 'gmail',
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'candidate_run_id' => $result->run->id,
        'candidate_count' => 2,
    ]);
    expect($artifact['generated_at'])->toBe('2026-04-20T15:45:00+02:00');
    expect($artifact['candidates'])->toHaveCount(2);
    $firstCandidate = $artifact['candidates'][0] ?? null;

    if (! is_array($firstCandidate)) {
        throw new RuntimeException('Expected first biography candidate.');
    }

    expect($firstCandidate)->toMatchArray([
        'candidate_id' => 'chronology_candidate:gmail_messages:msg-old',
        'candidate_type' => 'chronology_candidate',
        'review_status' => 'unreviewed',
        'occurred_at' => '2026-04-18T08:30:00+00:00',
        'occurred_at_basis' => 'gmail.received_at',
        'provenance_ref' => 'gmail_messages:msg-old',
        'message_id' => 'msg-old',
        'thread_id' => 'thread-shared',
        'from' => 'Sender <sender@example.com>',
        'to' => 'odinn@example.com',
        'cc' => '',
        'subject' => 'Older contract question',
        'selection_reason' => 'Direct question needing review.',
        'snippet' => 'Can you review this?',
        'next_action' => 'Decide whether this belongs in the biography timeline.',
        'confidence' => 0.91,
        'labels' => ['INBOX', 'IMPORTANT'],
    ]);

    expect(RunArtifact::query()
        ->where('run_id', $result->run->id)
        ->where('artifact_kind', 'muninn-biography-candidates')
        ->where('locator', $result->candidatePath)
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

    $oldCandidateLink = ProvenanceLink::query()
        ->where('run_id', $result->run->id)
        ->where('evidence_ref', 'gmail_messages:msg-old')
        ->firstOrFail();

    expect($oldCandidateLink->output_target)
        ->toBe($result->candidatePath.'#candidates.chronology_candidate:gmail_messages:msg-old');
    expect($oldCandidateLink->metadata)->toMatchArray([
        'message_id' => 'msg-old',
        'thread_id' => 'thread-shared',
        'occurred_at' => '2026-04-18T08:30:00+00:00',
        'occurred_at_basis' => 'gmail.received_at',
    ]);
});

it('orders candidates by occurred at and then candidate id', function (): void {
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
                messageId: 'msg-b',
                threadId: 'thread-b',
                subject: 'Tie B message',
                receivedAt: '2026-04-20T11:00:00+02:00',
            ),
            gmailManualAnalysisBundleItem(
                messageId: 'msg-a',
                threadId: 'thread-a',
                subject: 'Tie A message',
                receivedAt: '2026-04-20T11:00:00+02:00',
            ),
        ],
    ]);

    $result = app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath);
    $artifact = decodeGmailBiographyCandidateArtifact($result->candidatePath);

    expect(array_column($artifact['candidates'], 'candidate_id'))->toBe([
        'chronology_candidate:gmail_messages:msg-a',
        'chronology_candidate:gmail_messages:msg-b',
        'chronology_candidate:gmail_messages:msg-utc',
    ]);
});

it('writes an empty candidate artifact with no provenance links', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'items' => [],
    ]);

    $result = app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath);
    $artifact = decodeGmailBiographyCandidateArtifact($result->candidatePath);

    expect($result->candidateCount)->toBe(0);
    expect($artifact['candidate_count'])->toBe(0);
    expect($artifact['candidates'])->toBe([]);
    expect(ProvenanceLink::query()->where('run_id', $result->run->id)->count())->toBe(0);
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

    expect(fn () => app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath))
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

    expect(fn () => app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath))
        ->toThrow(InvalidArgumentException::class, 'Only gmail-important-mail evidence bundles are supported.');
});

it('rejects unsupported source types', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_type' => 'chatgpt',
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
    ]);

    expect(fn () => app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath))
        ->toThrow(InvalidArgumentException::class, 'Only gmail source evidence bundles are supported.');
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
        'idempotency_key' => 'gmail-import:biography-candidates-other-test',
        'started_at' => now(),
        'finished_at' => now(),
    ]);
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $otherSourceRun->id,
        'evidence_run_id' => $runs['evidence']->id,
    ]);

    expect(fn () => app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath))
        ->toThrow(InvalidArgumentException::class, 'Evidence run referenced by bundle does not belong to the source Gmail import run.');
});

it('reruns by refreshing the artifact and candidate provenance without duplicate links', function (): void {
    $runs = createManualAnalysisBundleRuns();
    $bundlePath = writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'items' => [
            gmailManualAnalysisBundleItem(
                messageId: 'msg-first',
                threadId: 'thread-first',
                subject: 'First candidate',
                receivedAt: '2026-04-18T08:30:00+00:00',
            ),
            gmailManualAnalysisBundleItem(
                messageId: 'msg-removed',
                threadId: 'thread-removed',
                subject: 'Removed candidate',
                receivedAt: '2026-04-19T08:30:00+00:00',
            ),
        ],
    ]);

    $firstResult = app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath);

    writeGmailManualAnalysisBundle([
        'source_run_id' => $runs['source']->id,
        'evidence_run_id' => $runs['evidence']->id,
        'items' => [
            gmailManualAnalysisBundleItem(
                messageId: 'msg-replacement',
                threadId: 'thread-replacement',
                subject: 'Replacement candidate',
                receivedAt: '2026-04-20T08:30:00+00:00',
            ),
        ],
    ]);

    $secondResult = app(BuildGmailBiographyCandidateArtifactAction::class)($bundlePath);
    $artifact = decodeGmailBiographyCandidateArtifact($secondResult->candidatePath);

    expect($secondResult->run->id)->toBe($firstResult->run->id);
    expect($artifact['candidate_count'])->toBe(1);
    expect(array_column($artifact['candidates'], 'candidate_id'))->toBe([
        'chronology_candidate:gmail_messages:msg-replacement',
    ]);
    expect(ProvenanceLink::query()
        ->where('run_id', $secondResult->run->id)
        ->orderBy('id')
        ->pluck('evidence_ref')
        ->all())->toBe([
            'gmail_messages:msg-replacement',
        ]);
    expect(RunArtifact::query()
        ->where('run_id', $secondResult->run->id)
        ->where('artifact_kind', 'muninn-biography-candidates')
        ->count())->toBe(1);
    expect(RunArtifact::query()
        ->where('run_id', $secondResult->run->id)
        ->where('artifact_kind', 'muninn-biography-candidates')
        ->firstOrFail()
        ->metadata)->toMatchArray([
            'candidate_count' => 1,
        ]);
});

/**
 * @return array{schema_version:int, artifact_type:string, generated_at:string, source_bundle_path:string, source_bundle_type:string, source_type:string, source_run_id:int, evidence_run_id:int, candidate_run_id:int, candidate_count:int, candidates:list<array<string, mixed>>}
 */
function decodeGmailBiographyCandidateArtifact(string $path): array
{
    $decoded = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException('Candidate artifact JSON did not decode to an object.');
    }

    foreach (['schema_version', 'artifact_type', 'generated_at', 'source_bundle_path', 'source_bundle_type', 'source_type', 'source_run_id', 'evidence_run_id', 'candidate_run_id', 'candidate_count', 'candidates'] as $key) {
        if (! array_key_exists($key, $decoded)) {
            throw new RuntimeException("Candidate artifact JSON is missing [{$key}].");
        }
    }

    /** @var array{schema_version:int, artifact_type:string, generated_at:string, source_bundle_path:string, source_bundle_type:string, source_type:string, source_run_id:int, evidence_run_id:int, candidate_run_id:int, candidate_count:int, candidates:list<array<string, mixed>>} $decoded */
    return $decoded;
}
