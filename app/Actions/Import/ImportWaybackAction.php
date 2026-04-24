<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\WaybackImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use App\Services\Wayback\WaybackCaptureClassifier;
use App\Services\Wayback\WaybackClient;
use App\Services\Wayback\WaybackMirrorDownloader;
use App\Services\Wayback\WaybackScreenshotter;
use App\Services\Wayback\WaybackTextExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Throwable;

class ImportWaybackAction
{
    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly ProvenanceWriter $provenanceWriter,
        private readonly SourceObservationStore $observationStore,
        private readonly WaybackClient $client,
        private readonly WaybackTextExtractor $textExtractor,
        private readonly WaybackCaptureClassifier $classifier,
        private readonly WaybackScreenshotter $screenshotter,
        private readonly WaybackMirrorDownloader $mirrorDownloader,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): WaybackImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'wayback-import',
            import: fn (Run $run): array => $this->importCaptures($dispatchPayload, $run, $progress),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->importArtifactWriter->write($run, $dispatchPayload, 'wayback', 'wayback-import-summary', $summary);
                File::ensureDirectoryExists(base_path('data/imports/wayback'));
                File::put(
                    base_path('data/imports/wayback/wayback-import-summary-run-'.$run->id.'.json'),
                    json_encode(['run_id' => $run->id, 'summary' => $summary], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
                );
            },
        );

        return new WaybackImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function importCaptures(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        if ($dispatchPayload->accessMode !== 'web-api') {
            throw new InvalidArgumentException('Wayback imports require web-api access.');
        }

        $scope = $dispatchPayload->sourceLocator;
        $matchMode = (string) ($dispatchPayload->scopeSnapshot['match_mode'] ?? 'host');
        $from = $this->nullableString($dispatchPayload->scopeSnapshot['from'] ?? null);
        $to = $this->nullableString($dispatchPayload->scopeSnapshot['to'] ?? null);
        $limit = (int) ($dispatchPayload->scopeSnapshot['limit'] ?? 100);
        $delayMs = (int) ($dispatchPayload->importerOptions['delay_ms'] ?? 2000);
        $withScreenshots = (bool) ($dispatchPayload->importerOptions['with_screenshots'] ?? false);
        $mirrorAssets = (bool) ($dispatchPayload->importerOptions['mirror_assets'] ?? false);

        $scopeId = $this->upsertScope($scope, $matchMode, $from, $to);
        $captures = $this->client->cdxCaptures($scope, $matchMode, $from, $to, $limit, $delayMs);

        $summary = [
            'source_file' => $scope,
            'scope_id' => $scopeId,
            'cdx_captures' => count($captures),
            'captures' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'failed' => 0,
            'screenshots' => 0,
            'mirrors' => 0,
        ];

        foreach ($captures as $cdx) {
            $timestamp = (string) ($cdx['timestamp'] ?? '');
            $originalUrl = (string) ($cdx['original'] ?? '');

            if ($timestamp === '' || $originalUrl === '') {
                continue;
            }

            $replayUrl = $this->client->replayUrl($timestamp, $originalUrl);

            if ($this->isDefaultExcludedUrl($originalUrl)) {
                $this->upsertRejectedCapture($scopeId, $timestamp, $originalUrl, $replayUrl, $cdx, 'excluded-url');
                $summary['captures']++;
                $summary['rejected']++;

                if (is_callable($progress)) {
                    $progress('wayback_capture_rejected', ['captures' => $summary['captures']]);
                }

                continue;
            }

            try {
                $html = $this->client->replayHtml($timestamp, $originalUrl, $delayMs);
            } catch (Throwable $throwable) {
                $this->upsertFailedCapture($scopeId, $timestamp, $originalUrl, $replayUrl, $cdx, $throwable);
                $summary['captures']++;
                $summary['failed']++;

                if (is_callable($progress)) {
                    $progress('wayback_capture_failed', ['captures' => $summary['captures']]);
                }

                continue;
            }

            $html = $this->normalizeReplayHtml($html);
            $extracted = $this->textExtractor->extract($html);
            $classification = $this->classifier->classify($originalUrl, $html, $extracted['authored_text']);
            $captureId = $this->upsertCapture(
                $scopeId,
                $timestamp,
                $originalUrl,
                $replayUrl,
                $cdx,
                $html,
                $extracted,
                $classification,
            );

            $summary['captures']++;

            if ($classification['verdict'] === 'accepted') {
                $summary['accepted']++;
                $this->provenanceWriter->link(new WriteProvenanceLinkData(
                    runId: $run->id,
                    outputTarget: 'wayback_captures:'.$captureId,
                    claimKey: 'imported-wayback-biographical-capture',
                    evidenceType: 'wayback-replay',
                    evidenceRef: $replayUrl,
                ));
            } else {
                $summary['rejected']++;
            }

            if ($withScreenshots && $this->hydrateScreenshot($run, $captureId, $scopeId, $timestamp, $replayUrl)) {
                $summary['screenshots']++;
            }

            if ($mirrorAssets && $this->hydrateMirror($run, $captureId, $scopeId, $timestamp, $replayUrl)) {
                $summary['mirrors']++;
            }

            if (is_callable($progress)) {
                $progress('wayback_capture_completed', ['captures' => $summary['captures']]);
            }
        }

        return $summary;
    }

    private function upsertScope(string $scope, string $matchMode, ?string $from, ?string $to): int
    {
        $parts = $this->scopeParts($scope);

        $sourceKey = sha1($scope.'|'.$matchMode.'|'.($from ?? '').'|'.($to ?? ''));

        return $this->observationStore->upsertAndReturnId('wayback_scopes', [
            'source_key' => $sourceKey,
        ], [
            'scope' => $scope,
            'match_mode' => $matchMode,
            'host' => $parts['host'],
            'path' => $parts['path'],
            'from_timestamp' => $from,
            'to_timestamp' => $to,
            'filter_policy' => json_encode([
                'statuscode' => 200,
                'mimetype' => 'text/html',
                'collapse' => 'digest',
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, mixed>  $cdx
     */
    private function upsertRejectedCapture(
        int $scopeId,
        string $timestamp,
        string $originalUrl,
        string $replayUrl,
        array $cdx,
        string $rejectReason,
    ): int {
        return $this->observationStore->upsertAndReturnId('wayback_captures', [
            'wayback_scope_id' => $scopeId,
            'timestamp' => $timestamp,
            'original_url_hash' => hash('sha256', $originalUrl),
        ], [
            'captured_at' => $this->capturedAt($timestamp)->format('Y-m-d H:i:s'),
            'original_url' => $originalUrl,
            'replay_url' => $replayUrl,
            'cdx_fields' => json_encode($cdx, JSON_THROW_ON_ERROR),
            'page_key' => hash('sha256', $originalUrl),
            'digest' => $this->nullableString($cdx['digest'] ?? null),
            'verdict' => 'rejected',
            'reject_reason' => $rejectReason,
            'raw_replay_html' => null,
            'extracted_authored_text' => null,
            'title' => null,
            'meta_description' => null,
            'retrieval_metadata' => json_encode([
                'retrieved_at' => now()->toISOString(),
                'source' => 'internet-archive-wayback',
                'skipped_replay_fetch' => true,
            ], JSON_THROW_ON_ERROR),
            'raw_cdx_json' => json_encode($cdx, JSON_THROW_ON_ERROR),
            'biographical_surface' => null,
            'timeline_anchor_date' => $this->capturedAt($timestamp)->toDateString(),
            'evidence_summary' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cdx
     */
    private function upsertFailedCapture(
        int $scopeId,
        string $timestamp,
        string $originalUrl,
        string $replayUrl,
        array $cdx,
        Throwable $throwable,
    ): int {
        return $this->observationStore->upsertAndReturnId('wayback_captures', [
            'wayback_scope_id' => $scopeId,
            'timestamp' => $timestamp,
            'original_url_hash' => hash('sha256', $originalUrl),
        ], [
            'captured_at' => $this->capturedAt($timestamp)->format('Y-m-d H:i:s'),
            'original_url' => $originalUrl,
            'replay_url' => $replayUrl,
            'cdx_fields' => json_encode($cdx, JSON_THROW_ON_ERROR),
            'page_key' => hash('sha256', $originalUrl),
            'digest' => $this->nullableString($cdx['digest'] ?? null),
            'verdict' => 'failed',
            'reject_reason' => 'replay-fetch-failed',
            'raw_replay_html' => null,
            'extracted_authored_text' => null,
            'title' => null,
            'meta_description' => null,
            'retrieval_metadata' => json_encode([
                'retrieved_at' => now()->toISOString(),
                'source' => 'internet-archive-wayback',
                'failure' => $this->summarizeFailure($throwable),
            ], JSON_THROW_ON_ERROR),
            'raw_cdx_json' => json_encode($cdx, JSON_THROW_ON_ERROR),
            'biographical_surface' => null,
            'timeline_anchor_date' => $this->capturedAt($timestamp)->toDateString(),
            'evidence_summary' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cdx
     * @param  array{title:?string, meta_description:?string, authored_text:string}  $extracted
     * @param  array{verdict:string, reject_reason:?string, biographical_surface:?string, evidence_summary:?string}  $classification
     */
    private function upsertCapture(
        int $scopeId,
        string $timestamp,
        string $originalUrl,
        string $replayUrl,
        array $cdx,
        string $html,
        array $extracted,
        array $classification,
    ): int {
        return $this->observationStore->upsertAndReturnId('wayback_captures', [
            'wayback_scope_id' => $scopeId,
            'timestamp' => $timestamp,
            'original_url_hash' => hash('sha256', $originalUrl),
        ], [
            'captured_at' => $this->capturedAt($timestamp)->format('Y-m-d H:i:s'),
            'original_url' => $originalUrl,
            'replay_url' => $replayUrl,
            'cdx_fields' => json_encode($cdx, JSON_THROW_ON_ERROR),
            'page_key' => hash('sha256', $originalUrl),
            'digest' => $this->nullableString($cdx['digest'] ?? null),
            'verdict' => $classification['verdict'],
            'reject_reason' => $classification['reject_reason'],
            'raw_replay_html' => $html,
            'extracted_authored_text' => $extracted['authored_text'],
            'title' => $extracted['title'],
            'meta_description' => $extracted['meta_description'],
            'retrieval_metadata' => json_encode([
                'retrieved_at' => now()->toISOString(),
                'source' => 'internet-archive-wayback',
            ], JSON_THROW_ON_ERROR),
            'raw_cdx_json' => json_encode($cdx, JSON_THROW_ON_ERROR),
            'biographical_surface' => $classification['biographical_surface'],
            'timeline_anchor_date' => $this->capturedAt($timestamp)->toDateString(),
            'evidence_summary' => $classification['evidence_summary'],
        ]);
    }

    private function hydrateScreenshot(Run $run, int $captureId, int $scopeId, string $timestamp, string $replayUrl): bool
    {
        $capture = DB::table('wayback_captures')->where('id', $captureId)->first();

        if ($capture !== null && is_string($capture->screenshot_path) && is_string($capture->screenshot_hash) && File::exists($capture->screenshot_path)) {
            return false;
        }

        $path = base_path('data/imports/wayback/screenshots/'.$scopeId.'/'.$timestamp.'-'.$captureId.'.png');

        try {
            $result = $this->screenshotter->capture($replayUrl, $path);
        } catch (Throwable) {
            return false;
        }

        DB::table('wayback_captures')->where('id', $captureId)->update([
            'screenshot_path' => $result['path'],
            'screenshot_hash' => $result['hash'],
            'updated_at' => now(),
        ]);

        $this->provenanceWriter->link(new WriteProvenanceLinkData(
            runId: $run->id,
            outputTarget: 'wayback_captures:'.$captureId,
            claimKey: 'hydrated-wayback-screenshot',
            evidenceType: 'screenshot',
            evidenceRef: $result['path'],
        ));

        return true;
    }

    private function hydrateMirror(Run $run, int $captureId, int $scopeId, string $timestamp, string $replayUrl): bool
    {
        $capture = DB::table('wayback_captures')->where('id', $captureId)->first();

        if ($capture !== null && is_string($capture->mirror_path) && File::isDirectory($capture->mirror_path)) {
            return false;
        }

        $path = base_path('data/imports/wayback/mirrors/'.$scopeId.'/'.$timestamp.'-'.$captureId);
        $result = $this->mirrorDownloader->mirror($replayUrl, $path);

        DB::table('wayback_captures')->where('id', $captureId)->update([
            'mirror_path' => $result,
            'updated_at' => now(),
        ]);

        $this->provenanceWriter->link(new WriteProvenanceLinkData(
            runId: $run->id,
            outputTarget: 'wayback_captures:'.$captureId,
            claimKey: 'hydrated-wayback-mirror',
            evidenceType: 'mirror',
            evidenceRef: $result,
        ));

        return true;
    }

    /**
     * @return array{host:?string, path:?string}
     */
    private function scopeParts(string $scope): array
    {
        $withScheme = str_contains($scope, '://') ? $scope : 'https://'.$scope;

        return [
            'host' => $this->nullableString(parse_url($withScheme, PHP_URL_HOST)),
            'path' => $this->nullableString(parse_url($withScheme, PHP_URL_PATH)),
        ];
    }

    private function capturedAt(string $timestamp): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('YmdHis', $timestamp, 'UTC')
            ?: CarbonImmutable::parse('1970-01-01 00:00:00', 'UTC');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function normalizeReplayHtml(string $html): string
    {
        if (mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        return mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
    }

    private function isDefaultExcludedUrl(string $originalUrl): bool
    {
        $path = strtolower((string) (parse_url($originalUrl, PHP_URL_PATH) ?? ''));

        return in_array($path, [
            '/cgi-bin/count.cgi',
            '/cgi-bin/nph-count',
        ], true);
    }

    /**
     * @return array{class:string, message:string, code:int|string}
     */
    private function summarizeFailure(Throwable $throwable): array
    {
        return [
            'class' => $throwable::class,
            'message' => mb_strimwidth($throwable->getMessage(), 0, 500, '...'),
            'code' => $throwable->getCode(),
        ];
    }
}
