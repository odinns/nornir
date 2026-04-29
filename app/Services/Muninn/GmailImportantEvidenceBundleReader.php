<?php

declare(strict_types=1);

namespace App\Services\Muninn;

use App\Models\Run;
use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * @phpstan-type GmailImportantEvidenceBundleItem array{message_id:string, thread_id:string, from:string, to:string, cc:string, subject:string, received_at:string, urgency:string, reason:string, next_action:string, confidence:float|int, labels:list<string>, snippet:string, body_plain:string, body_html:string, provenance_ref:string}
 * @phpstan-type GmailImportantEvidenceBundle array{schema_version:1, bundle_type:'gmail-important-mail', source_type:'gmail', source_run_id:int, evidence_run_id:int, generated_at:string, account_email:string, source_set_ids:list<int>, query:string, selection:array{mode:string, limit:int, matched_count:int}, items:list<GmailImportantEvidenceBundleItem>}
 * @phpstan-type ReadGmailImportantEvidenceBundle array{path:string, bundle:GmailImportantEvidenceBundle}
 */
class GmailImportantEvidenceBundleReader
{
    /**
     * @return ReadGmailImportantEvidenceBundle
     */
    public function read(string $bundlePath): array
    {
        $bundlePath = $this->resolvePath($bundlePath);

        if (! File::exists($bundlePath)) {
            throw new InvalidArgumentException('Evidence bundle not found: '.$bundlePath);
        }

        try {
            $decoded = json_decode((string) File::get($bundlePath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Evidence bundle JSON could not be decoded: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Evidence bundle JSON must decode to an object.');
        }

        if (($decoded['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Only evidence bundle schema_version 1 is supported.');
        }

        if (($decoded['bundle_type'] ?? null) !== 'gmail-important-mail') {
            throw new InvalidArgumentException('Only gmail-important-mail evidence bundles are supported.');
        }

        $this->assertBundleShape($decoded);
        $this->assertRunChain($decoded);

        /** @var GmailImportantEvidenceBundle $decoded */
        return [
            'path' => $bundlePath,
            'bundle' => $decoded,
        ];
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new InvalidArgumentException('Evidence bundle path is required.');
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<mixed>  $bundle
     *
     * @phpstan-assert GmailImportantEvidenceBundle $bundle
     */
    private function assertBundleShape(array $bundle): void
    {
        $sourceType = $bundle['source_type'] ?? null;
        $generatedAt = $bundle['generated_at'] ?? null;
        $accountEmail = $bundle['account_email'] ?? null;
        $query = $bundle['query'] ?? null;

        if (! is_string($sourceType)) {
            throw new InvalidArgumentException('Gmail evidence bundle field [source_type] must be a string.');
        }

        if (! is_string($generatedAt)) {
            throw new InvalidArgumentException('Gmail evidence bundle field [generated_at] must be a string.');
        }

        if (! is_string($accountEmail)) {
            throw new InvalidArgumentException('Gmail evidence bundle field [account_email] must be a string.');
        }

        if (! is_string($query)) {
            throw new InvalidArgumentException('Gmail evidence bundle field [query] must be a string.');
        }

        if ($sourceType !== 'gmail') {
            throw new InvalidArgumentException('Only gmail source evidence bundles are supported.');
        }

        $this->assertIsoTimestamp($generatedAt, 'Gmail evidence bundle field [generated_at] must be an ISO 8601 timestamp.');

        foreach (['source_run_id', 'evidence_run_id'] as $key) {
            if (! is_int($bundle[$key] ?? null)) {
                throw new InvalidArgumentException("Gmail evidence bundle field [{$key}] must be an integer.");
            }
        }

        if (! is_array($bundle['source_set_ids'] ?? null) || ! array_is_list($bundle['source_set_ids']) || ! $this->allIntegers($bundle['source_set_ids'])) {
            throw new InvalidArgumentException('Gmail evidence bundle field [source_set_ids] must be a list of integers.');
        }

        if (! is_array($bundle['selection'] ?? null)) {
            throw new InvalidArgumentException('Gmail evidence bundle field [selection] must be an object.');
        }

        $selection = $bundle['selection'];

        if (
            ! is_string($selection['mode'] ?? null)
            || ! is_int($selection['limit'] ?? null)
            || ! is_int($selection['matched_count'] ?? null)
        ) {
            throw new InvalidArgumentException('Gmail evidence bundle field [selection] is incomplete.');
        }

        if (! is_array($bundle['items'] ?? null) || ! array_is_list($bundle['items'])) {
            throw new InvalidArgumentException('Gmail evidence bundle field [items] must be a list.');
        }

        foreach ($bundle['items'] as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] must be an object.");
            }

            $this->assertItemShape($item, $index);
        }
    }

    /**
     * @param  array<mixed>  $item
     *
     * @phpstan-assert GmailImportantEvidenceBundleItem $item
     */
    private function assertItemShape(array $item, int $index): void
    {
        foreach (['message_id', 'thread_id', 'from', 'to', 'cc', 'subject', 'received_at', 'urgency', 'reason', 'next_action', 'snippet', 'body_plain', 'body_html', 'provenance_ref'] as $key) {
            if (! is_string($item[$key] ?? null)) {
                throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [{$key}] must be a string.");
            }
        }

        if (! is_float($item['confidence'] ?? null) && ! is_int($item['confidence'] ?? null)) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [confidence] must be numeric.");
        }

        if (! is_array($item['labels'] ?? null) || ! array_is_list($item['labels']) || ! $this->allStrings($item['labels'])) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [labels] must be a list of strings.");
        }

        $messageId = $item['message_id'] ?? null;
        $provenanceRef = $item['provenance_ref'] ?? null;
        $receivedAt = $item['received_at'] ?? null;

        if (! is_string($messageId) || ! is_string($provenanceRef) || $provenanceRef !== 'gmail_messages:'.$messageId) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] provenance_ref must match gmail_messages:{message_id}.");
        }

        if (! is_string($receivedAt)) {
            throw new InvalidArgumentException("Gmail evidence bundle item [{$index}] field [received_at] must be a string.");
        }

        $this->assertIsoTimestamp($receivedAt, "Gmail evidence bundle item [{$index}] field [received_at] must be an ISO 8601 timestamp.");
    }

    /**
     * @param  GmailImportantEvidenceBundle  $bundle
     */
    private function assertRunChain(array $bundle): void
    {
        $sourceRun = Run::query()->whereKey($bundle['source_run_id'])->first();

        if (! $sourceRun instanceof Run) {
            throw new InvalidArgumentException('Source run referenced by bundle does not exist.');
        }

        if (
            $sourceRun->subsystem !== 'import'
            || $sourceRun->operation !== 'gmail-import'
            || $sourceRun->status !== Run::STATUS_SUCCEEDED
        ) {
            throw new InvalidArgumentException('Source run referenced by bundle is not a succeeded Gmail import run.');
        }

        $evidenceRun = Run::query()->whereKey($bundle['evidence_run_id'])->first();

        if (! $evidenceRun instanceof Run) {
            throw new InvalidArgumentException('Evidence run referenced by bundle does not exist.');
        }

        if (
            $evidenceRun->subsystem !== 'muninn'
            || $evidenceRun->operation !== 'gmail-important-evidence-bundle'
            || $evidenceRun->status !== Run::STATUS_SUCCEEDED
            || $evidenceRun->parent_run_id !== $sourceRun->id
            || ($evidenceRun->input_scope['source_run_id'] ?? null) !== $sourceRun->id
        ) {
            throw new InvalidArgumentException('Evidence run referenced by bundle does not belong to the source Gmail import run.');
        }
    }

    /**
     * @param  list<mixed>  $values
     */
    private function allIntegers(array $values): bool
    {
        return array_all($values, fn ($value): bool => is_int($value));
    }

    /**
     * @param  list<mixed>  $values
     */
    private function allStrings(array $values): bool
    {
        return array_all($values, fn ($value): bool => is_string($value));
    }

    private function assertIsoTimestamp(string $timestamp, string $message): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $timestamp) !== 1) {
            throw new InvalidArgumentException($message);
        }

        try {
            new DateTimeImmutable($timestamp);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException($message, $throwable->getCode(), previous: $throwable);
        }
    }
}
