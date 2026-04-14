<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\GmailImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Gmail\GmailClientFactory;
use App\Services\Nornir\ProvenanceWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportGmailAction
{
    private const string TABLE_SOURCE_SETS = 'gmail_source_sets';

    private const string TABLE_ACCOUNTS = 'gmail_accounts';

    private const string TABLE_THREADS = 'gmail_threads';

    private const string TABLE_MESSAGES = 'gmail_messages';

    private const string TABLE_LABELS = 'gmail_message_labels';

    private const string TABLE_ATTACHMENTS = 'gmail_attachments';

    private const string TABLE_MESSAGE_OBSERVATIONS = 'gmail_message_observations';

    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly ProvenanceWriter $provenanceWriter,
        private readonly GmailClientFactory $gmailClientFactory,
        private readonly SourceObservationStore $observationStore,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): GmailImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'gmail-import',
            import: fn (Run $run): array => $this->importMessages($dispatchPayload, $run, $progress),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->importArtifactWriter->write(
                    run: $run,
                    dispatchPayload: $dispatchPayload,
                    sourceType: 'gmail',
                    artifactKind: 'gmail-import-summary',
                    summary: $summary,
                );
            },
        );

        /** @var array{run: Run, summary: array<string, mixed>} $execution */
        return new GmailImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function importMessages(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        $query = (string) ($dispatchPayload->scopeSnapshot['query'] ?? '');

        $client = $this->gmailClientFactory->make($dispatchPayload->sourceLocator, '');
        $accountEmail = $client->getAccountEmail();

        $accountId = $this->upsertAccount($accountEmail);
        $sourceSetId = $this->upsertSourceSet($dispatchPayload, $accountEmail, $query);

        $threadCount = 0;
        $messageCount = 0;
        $insertedMessages = 0;
        $reobservedMessages = 0;
        $labelCount = 0;
        $attachmentCount = 0;

        $pageToken = null;

        do {
            $page = $client->listMessages($query, $pageToken);
            $stubs = $page['messages'];
            $pageToken = $page['nextPageToken'];

            $this->reportProgress($progress, 'messages_fetched', [
                'count' => count($stubs),
            ]);

            foreach ($stubs as $stub) {
                $messageId = $stub['id'];

                if ($messageId === '') {
                    throw new InvalidArgumentException('Malformed Gmail message: empty id field.');
                }

                $full = $client->getMessage($messageId);

                $threadId = (string) ($full['threadId'] ?? $stub['threadId']);
                $threadRowId = $this->upsertThread($accountId, $threadId, $full, $threadCount);

                $result = $this->upsertMessage($threadRowId, $messageId, $full);
                $messageCount++;

                if ($result['wasRecentlyCreated']) {
                    $insertedMessages++;
                } else {
                    $reobservedMessages++;
                }

                $synced = $this->syncLabels($result['id'], $full['labelIds'] ?? []);
                $labelCount += $synced;

                $added = $this->syncAttachments($result['id'], $full['payload'] ?? []);
                $attachmentCount += $added;

                $this->observationStore->record(
                    table: self::TABLE_MESSAGE_OBSERVATIONS,
                    unique: [
                        'gmail_message_id' => $result['id'],
                        'gmail_source_set_id' => $sourceSetId,
                    ],
                );

                $this->provenanceWriter->link(new WriteProvenanceLinkData(
                    runId: $run->id,
                    outputTarget: self::TABLE_MESSAGES.':'.$messageId,
                    claimKey: 'imported-message',
                    evidenceType: 'api-response',
                    evidenceRef: 'gmail-api#message:'.$messageId,
                ));

                $this->reportProgress($progress, 'message_completed', [
                    'messages' => $messageCount,
                    'threads' => $threadCount,
                ]);
            }
        } while ($pageToken !== null);

        return [
            'source_set_id' => $sourceSetId,
            'account_email' => $accountEmail,
            'threads' => $threadCount,
            'messages' => $messageCount,
            'inserted_messages' => $insertedMessages,
            'reobserved_messages' => $reobservedMessages,
            'labels' => $labelCount,
            'attachments' => $attachmentCount,
        ];
    }

    private function upsertAccount(string $accountEmail): int
    {
        return $this->observationStore->upsertAndReturnId(
            table: self::TABLE_ACCOUNTS,
            unique: ['account_key' => sha1($accountEmail)],
            values: ['account_email' => $accountEmail, 'access_mode' => 'api'],
        );
    }

    private function upsertSourceSet(ImporterDispatchData $dispatchPayload, string $accountEmail, string $query): int
    {
        $sourceKey = sha1(implode('|', [
            $dispatchPayload->accessMode,
            $dispatchPayload->sourceLocator,
            $accountEmail,
            $query,
        ]));

        return $this->observationStore->upsertAndReturnId(
            table: self::TABLE_SOURCE_SETS,
            unique: ['source_key' => $sourceKey],
            values: [
                'source_locator' => $dispatchPayload->sourceLocator,
                'access_mode' => $dispatchPayload->accessMode,
                'account_email' => $accountEmail,
                'query' => $query,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $fullMessage
     */
    private function upsertThread(int $accountId, string $threadId, array $fullMessage, int &$threadCount): int
    {
        $existing = DB::table(self::TABLE_THREADS)
            ->where('gmail_account_id', $accountId)
            ->where('thread_id', $threadId)
            ->value('id');

        if ($existing === null) {
            $threadCount++;
        }

        DB::table(self::TABLE_THREADS)->updateOrInsert(
            [
                'gmail_account_id' => $accountId,
                'thread_id' => $threadId,
            ],
            [
                'snippet' => isset($fullMessage['snippet']) ? (string) $fullMessage['snippet'] : null,
                'history_id' => isset($fullMessage['historyId']) ? (string) $fullMessage['historyId'] : null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table(self::TABLE_THREADS)
            ->where('gmail_account_id', $accountId)
            ->where('thread_id', $threadId)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $fullMessage
     * @return array{id: int, wasRecentlyCreated: bool}
     */
    private function upsertMessage(int $threadRowId, string $messageId, array $fullMessage): array
    {
        $existing = DB::table(self::TABLE_MESSAGES)->where('message_id', $messageId)->value('id');

        $headers = $this->extractHeaders($fullMessage['payload'] ?? []);
        $internalDate = isset($fullMessage['internalDate']) ? (int) $fullMessage['internalDate'] : null;

        DB::table(self::TABLE_MESSAGES)->updateOrInsert(
            ['message_id' => $messageId],
            [
                'gmail_thread_id' => $threadRowId,
                'from_header' => $headers['From'] ?? null,
                'to_header' => $headers['To'] ?? null,
                'cc_header' => $headers['Cc'] ?? null,
                'subject' => $headers['Subject'] ?? null,
                'snippet' => isset($fullMessage['snippet']) ? (string) $fullMessage['snippet'] : null,
                'body_plain' => $this->extractBody($fullMessage['payload'] ?? [], 'text/plain'),
                'body_html' => $this->extractBody($fullMessage['payload'] ?? [], 'text/html'),
                'raw_headers' => json_encode($fullMessage['payload']['headers'] ?? [], JSON_THROW_ON_ERROR),
                'internal_date' => $internalDate,
                'message_received_at' => $internalDate !== null
                    ? CarbonImmutable::createFromTimestampMsUTC($internalDate)->toDateTimeString()
                    : null,
                'raw_payload' => json_encode($fullMessage['payload'] ?? [], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return [
            'id' => (int) DB::table(self::TABLE_MESSAGES)->where('message_id', $messageId)->value('id'),
            'wasRecentlyCreated' => $existing === null,
        ];
    }

    /**
     * @param  list<string>  $labelIds
     */
    private function syncLabels(int $messageRowId, array $labelIds): int
    {
        foreach ($labelIds as $labelId) {
            $this->observationStore->record(
                table: self::TABLE_LABELS,
                unique: ['gmail_message_id' => $messageRowId, 'label_id' => $labelId],
                values: ['label_name' => $labelId],
            );
        }

        return count($labelIds);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncAttachments(int $messageRowId, array $payload): int
    {
        return $this->walkPartsForAttachments($messageRowId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function walkPartsForAttachments(int $messageRowId, array $payload): int
    {
        $added = 0;
        $parts = $payload['parts'] ?? [];

        if (! is_array($parts)) {
            return 0;
        }

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $body = $part['body'] ?? [];
            $attachmentId = $body['attachmentId'] ?? null;

            if (is_string($attachmentId) && $attachmentId !== '') {
                $filename = isset($part['filename']) && is_string($part['filename']) ? $part['filename'] : null;
                $mimeType = isset($part['mimeType']) && is_string($part['mimeType']) ? $part['mimeType'] : null;
                $size = isset($body['size']) && is_numeric($body['size']) ? (int) $body['size'] : null;

                DB::table(self::TABLE_ATTACHMENTS)->updateOrInsert(
                    [
                        'gmail_message_id' => $messageRowId,
                        'attachment_id' => $attachmentId,
                    ],
                    [
                        'filename' => $filename,
                        'mime_type' => $mimeType,
                        'size_bytes' => $size,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $added++;
            }

            if (! empty($part['parts'])) {
                $added += $this->walkPartsForAttachments($messageRowId, $part);
            }
        }

        return $added;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function extractHeaders(array $payload): array
    {
        $headers = [];
        $rawHeaders = $payload['headers'] ?? [];

        if (! is_array($rawHeaders)) {
            return $headers;
        }

        foreach ($rawHeaders as $header) {
            if (! is_array($header)) {
                continue;
            }

            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;

            if (is_string($name) && is_string($value)) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractBody(array $payload, string $mimeType): ?string
    {
        if (($payload['mimeType'] ?? null) === $mimeType) {
            $data = $payload['body']['data'] ?? null;

            if (is_string($data) && $data !== '') {
                return base64_decode(strtr($data, '-_', '+/')) ?: null;
            }
        }

        $parts = $payload['parts'] ?? [];

        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $result = $this->extractBody($part, $mimeType);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param  array<string, int|string>  $payload
     */
    private function reportProgress(?callable $progress, string $event, array $payload): void
    {
        if ($progress === null) {
            return;
        }

        $progress($event, $payload);
    }
}
