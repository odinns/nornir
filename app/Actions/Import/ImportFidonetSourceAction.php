<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\FidonetSourceConnectionResolver;
use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Data\Import\FidonetImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * @phpstan-type FidonetAreaRow object{
 *     id:int,
 *     code:string,
 *     name:string,
 *     source_type:?string,
 *     area_type:?string
 * }
 * @phpstan-type FidonetMessageRow object{
 *     id:int,
 *     msgno:int,
 *     external_id:?string,
 *     subject:string,
 *     from_name:string,
 *     from_address:?string,
 *     to_name:string,
 *     to_address:?string,
 *     body_text:string,
 *     reply_to_msgno:?int,
 *     reply_to_external_id:?string,
 *     reply1st_msgno:?int,
 *     replynext_msgno:?int,
 *     thread_key:?string,
 *     posted_at:mixed,
 *     arrived_at:mixed,
 *     raw_metadata_json:?string
 * }
 */
class ImportFidonetSourceAction
{
    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly ProvenanceWriter $provenanceWriter,
        private readonly FidonetSourceConnectionResolver $fidonetSourceConnectionResolver,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): FidonetImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'fidonet-import',
            import: fn (Run $run): array => DB::transaction(
                fn (): array => $this->importSource($dispatchPayload, $run, $progress)
            ),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->importArtifactWriter->write($run, $dispatchPayload, 'fidonet', 'fidonet-import-summary', $summary);
            },
        );

        return new FidonetImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array<string, int|string>
     */
    private function importSource(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        if ($dispatchPayload->accessMode !== 'database') {
            throw new InvalidArgumentException('FidoNet imports currently require database access via a GoldED env file.');
        }

        $source = $this->fidonetSourceConnectionResolver->connect($dispatchPayload->sourceLocator);
        $sourceConfig = $this->fidonetSourceConnectionResolver->resolveConfig($dispatchPayload->sourceLocator);
        $sourceId = $this->upsertSource($dispatchPayload, $sourceConfig);
        $summary = [
            'source_file' => basename($dispatchPayload->sourceLocator),
            'source_set_id' => $sourceId,
            'areas' => 0,
            'threads' => 0,
            'messages' => 0,
            'participants' => 0,
            'cleanup_rows' => 0,
            'test_like_messages' => 0,
        ];

        $areaRows = $this->resolveAreas($source, $dispatchPayload->scopeSnapshot);

        foreach ($areaRows as $areaRow) {
            $messages = $this->resolveSelectedMessagesForArea($source, $areaRow);

            if ($messages === []) {
                continue;
            }

            $this->upsertArea($sourceId, $areaRow);
            $this->recordAreaObservation($sourceId, $areaRow->code);
            $summary['areas']++;

            $threadGroups = $this->groupMessagesIntoThreads($messages, (string) $areaRow->code);

            foreach ($threadGroups as $threadGroup) {
                if (! $threadGroup['selected']) {
                    continue;
                }

                $threadId = $this->upsertThread($threadGroup, (string) $areaRow->code);
                $this->recordThreadObservation($sourceId, $threadId);
                $summary['threads']++;

                foreach ($threadGroup['messages'] as $order => $message) {
                    $canonicalMessageId = $message->external_id;

                    if (! is_string($canonicalMessageId) || $canonicalMessageId === '') {
                        $areaCode = (string) $areaRow->code;
                        throw new InvalidArgumentException(
                            "FidoNet message is missing stable external_id in area [{$areaCode}] msgno [{$message->msgno}]."
                        );
                    }

                    $this->upsertMessage($sourceId, (string) $areaRow->code, $message);
                    $this->recordMessageObservation($sourceId, $canonicalMessageId);
                    $this->linkThreadMessage($threadId, $canonicalMessageId, $order + 1);
                    $this->syncParticipants($canonicalMessageId, $message);
                    $cleanup = $this->cleanupBody((string) $message->body_text, (string) $areaRow->code, $message);
                    $this->upsertCleanup($canonicalMessageId, $cleanup);
                    $this->provenanceWriter->link(new WriteProvenanceLinkData(
                        runId: $run->id,
                        outputTarget: 'fidonet_messages:'.$canonicalMessageId,
                        claimKey: 'imported-message',
                        evidenceType: 'canonical-message',
                        evidenceRef: $canonicalMessageId,
                    ));

                    $summary['messages']++;
                    $summary['cleanup_rows']++;

                    if ($cleanup['is_test_like']) {
                        $summary['test_like_messages']++;
                    }
                }
            }
        }

        $summary['participants'] = (int) DB::table('fidonet_participants')->count();
        $summary['threads'] = (int) DB::table('fidonet_threads')->count();

        if (is_callable($progress)) {
            $progress('fidonet_import_complete', $summary);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $scopeSnapshot
     * @return list<FidonetAreaRow>
     */
    private function resolveAreas(ConnectionInterface $source, array $scopeSnapshot): array
    {
        $query = $source->table('areas')
            ->select(['id', 'code', 'name', 'source_type', 'area_type'])
            ->orderBy('code');

        $includeCodes = $this->strings($scopeSnapshot['area_include_codes'] ?? []);
        $excludeCodes = $this->strings($scopeSnapshot['area_exclude_codes'] ?? []);

        if ($includeCodes !== []) {
            $query->whereIn('code', $includeCodes);
        }

        if ($excludeCodes !== []) {
            $query->whereNotIn('code', $excludeCodes);
        }

        /** @var list<FidonetAreaRow> $areas */
        $areas = array_values($query->get()->all());

        return $areas;
    }

    /**
     * @param  FidonetAreaRow  $areaRow
     * @return list<FidonetMessageRow>
     */
    private function resolveSelectedMessagesForArea(ConnectionInterface $source, object $areaRow): array
    {
        $messages = $source->table('messages')
            ->select([
                'id',
                'msgno',
                'external_id',
                'subject',
                'from_name',
                'from_address',
                'to_name',
                'to_address',
                'body_text',
                'reply_to_msgno',
                'reply_to_external_id',
                'reply1st_msgno',
                'replynext_msgno',
                'thread_key',
                'posted_at',
                'arrived_at',
                'raw_metadata_json',
            ])
            ->where('area_id', $areaRow->id)
            ->orderBy('msgno')
            ->get()
            ->all();

        /** @var list<FidonetMessageRow> $messages */
        $messages = array_values($messages);

        $threadGroups = $this->groupMessagesIntoThreads($messages, $areaRow->code);
        $selected = [];

        foreach ($threadGroups as $threadGroup) {
            if (! $threadGroup['selected']) {
                continue;
            }

            foreach ($threadGroup['messages'] as $message) {
                $selected[] = $message;
            }
        }

        /** @var list<FidonetMessageRow> $selected */
        return $selected;
    }

    /**
     * @param  list<FidonetMessageRow>  $messages
     * @return list<array{
     *     derived_thread_key:string,
     *     source_method:string,
     *     is_synthetic:bool,
     *     confidence:?string,
     *     messages:list<FidonetMessageRow>,
     *     selected:bool
     * }>
     */
    private function groupMessagesIntoThreads(array $messages, string $areaCode): array
    {
        $messagesByExternalId = [];
        $messagesByMsgno = [];
        $referencedExternalIds = [];
        $referencedMsgnos = [];

        foreach ($messages as $message) {
            if (is_string($message->external_id) && $message->external_id !== '') {
                $messagesByExternalId[$message->external_id] = $message;
            }

            $messagesByMsgno[(int) $message->msgno] = $message;

            if (is_string($message->reply_to_external_id) && trim($message->reply_to_external_id) !== '') {
                $referencedExternalIds[trim($message->reply_to_external_id)] = true;
            }

            if (is_numeric($message->reply_to_msgno)) {
                $referencedMsgnos[(int) $message->reply_to_msgno] = true;
            }
        }

        /** @var array<string, array{derived_thread_key:string, source_method:string, is_synthetic:bool, confidence:string|null, messages:list<object{id:int, msgno:int, external_id:string|null, subject:string, from_name:string, from_address:string|null, to_name:string, to_address:string|null, body_text:string, reply_to_msgno:int|null, reply_to_external_id:string|null, reply1st_msgno:int|null, replynext_msgno:int|null, thread_key:string|null, posted_at:mixed, arrived_at:mixed, raw_metadata_json:string|null}>, selected:bool}> $groups */
        $groups = [];

        foreach ($messages as $message) {
            $threadKey = is_string($message->thread_key) ? trim($message->thread_key) : '';

            if ($threadKey !== '') {
                $groupKey = 'thread-key:'.$threadKey;
                $sourceMethod = 'thread_key';
                $isSynthetic = false;
            } else {
                $rootToken = $this->resolveReplyChainRootToken(
                    $message,
                    $messagesByExternalId,
                    $messagesByMsgno,
                    $referencedExternalIds,
                    $referencedMsgnos,
                );
                $isSynthetic = $rootToken === 'singleton:'.(($message->external_id ?? $message->msgno));
                $groupKey = $rootToken;
                $sourceMethod = $isSynthetic ? 'singleton' : 'reply_chain';
            }

            $derivedThreadKey = sha1($areaCode.'|'.$groupKey);

            if (! array_key_exists($derivedThreadKey, $groups)) {
                $groups[$derivedThreadKey] = [
                    'derived_thread_key' => $derivedThreadKey,
                    'source_method' => $sourceMethod,
                    'is_synthetic' => $isSynthetic,
                    'confidence' => $sourceMethod === 'thread_key' ? 'high' : ($isSynthetic ? 'low' : 'medium'),
                    'messages' => [],
                    'selected' => false,
                ];
            }

            $groups[$derivedThreadKey]['messages'][] = $message;

            if ($this->isOdinnCandidate((string) $message->from_name, $message->from_address)) {
                $groups[$derivedThreadKey]['selected'] = true;
            }
        }

        foreach ($groups as &$group) {
            usort($group['messages'], static function (object $left, object $right): int {
                return $left->msgno <=> $right->msgno;
            });
        }

        unset($group);

        return array_values($groups);
    }

    /**
     * @param  FidonetMessageRow  $message
     * @param  array<string, FidonetMessageRow>  $messagesByExternalId
     * @param  array<int, FidonetMessageRow>  $messagesByMsgno
     * @param  array<string, bool>  $referencedExternalIds
     * @param  array<int, bool>  $referencedMsgnos
     */
    private function resolveReplyChainRootToken(
        object $message,
        array $messagesByExternalId,
        array $messagesByMsgno,
        array $referencedExternalIds,
        array $referencedMsgnos,
    ): string {
        $current = $message;
        $visited = [];

        while (true) {
            $token = is_string($current->external_id) && $current->external_id !== ''
                ? 'external:'.$current->external_id
                : 'msgno:'.$current->msgno;

            if (isset($visited[$token])) {
                return $token;
            }

            $visited[$token] = true;

            $replyExternalId = is_string($current->reply_to_external_id) ? trim($current->reply_to_external_id) : '';

            if ($replyExternalId !== '' && isset($messagesByExternalId[$replyExternalId])) {
                $current = $messagesByExternalId[$replyExternalId];

                continue;
            }

            $replyMsgno = is_numeric($current->reply_to_msgno) ? (int) $current->reply_to_msgno : null;

            if ($replyMsgno !== null && isset($messagesByMsgno[$replyMsgno])) {
                $current = $messagesByMsgno[$replyMsgno];

                continue;
            }

            if (is_string($message->external_id) && $message->external_id !== '') {
                $hasChildren = isset($referencedExternalIds[$message->external_id])
                    || isset($referencedMsgnos[(int) $message->msgno]);

                return $current === $message && ! $hasChildren
                    ? 'singleton:'.$message->external_id
                    : $token;
            }

            $hasChildren = isset($referencedMsgnos[(int) $message->msgno]);

            return $current === $message && ! $hasChildren ? 'singleton:'.$message->msgno : $token;
        }
    }

    /**
     * @param  array<string, mixed>  $sourceConfig
     */
    private function upsertSource(ImporterDispatchData $dispatchPayload, array $sourceConfig): int
    {
        $scopeHash = $this->scopeHash($dispatchPayload->scopeSnapshot);

        DB::table('fidonet_sources')->updateOrInsert(
            [
                'source_locator' => $dispatchPayload->sourceLocator,
                'scope_hash' => $scopeHash,
            ],
            [
                'access_mode' => $dispatchPayload->accessMode,
                'driver' => (string) ($sourceConfig['driver'] ?? ''),
                'database_name' => (string) ($sourceConfig['database'] ?? ''),
                'host' => isset($sourceConfig['host']) ? (string) $sourceConfig['host'] : null,
                'port' => isset($sourceConfig['port']) ? (string) $sourceConfig['port'] : null,
                'username' => isset($sourceConfig['username']) ? (string) $sourceConfig['username'] : null,
                'scope_snapshot' => json_encode($dispatchPayload->scopeSnapshot, JSON_THROW_ON_ERROR),
                'scope_hash' => $scopeHash,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('fidonet_sources')
            ->where('source_locator', $dispatchPayload->sourceLocator)
            ->where('scope_hash', $scopeHash)
            ->value('id');
    }

    /**
     * @param  FidonetAreaRow  $areaRow
     */
    private function upsertArea(int $sourceId, object $areaRow): void
    {
        DB::table('fidonet_areas')->updateOrInsert(
            ['area_code' => (string) $areaRow->code],
            [
                'fidonet_source_id' => $sourceId,
                'area_name' => (string) $areaRow->name,
                'source_type' => is_string($areaRow->source_type) ? $areaRow->source_type : null,
                'area_type' => is_string($areaRow->area_type) ? $areaRow->area_type : null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * @param  array{
     *     derived_thread_key:string,
     *     source_method:string,
     *     is_synthetic:bool,
     *     confidence:?string,
     *     messages:list<FidonetMessageRow>,
     *     selected:bool
     * }  $threadGroup
     */
    private function upsertThread(array $threadGroup, string $areaCode): int
    {
        DB::table('fidonet_threads')->updateOrInsert(
            ['derived_thread_key' => $threadGroup['derived_thread_key']],
            [
                'area_code' => $areaCode,
                'source_method' => $threadGroup['source_method'],
                'message_count' => count($threadGroup['messages']),
                'is_synthetic' => $threadGroup['is_synthetic'],
                'confidence' => $threadGroup['confidence'],
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('fidonet_threads')
            ->where('derived_thread_key', $threadGroup['derived_thread_key'])
            ->value('id');
    }

    /**
     * @param  FidonetMessageRow  $message
     */
    private function upsertMessage(int $sourceId, string $areaCode, object $message): void
    {
        $canonicalMessageId = (string) $message->external_id;

        DB::table('fidonet_messages')->updateOrInsert(
            ['canonical_message_id' => $canonicalMessageId],
            [
                'fidonet_source_id' => $sourceId,
                'area_code' => $areaCode,
                'source_message_row_id' => (int) $message->id,
                'source_msgno' => (int) $message->msgno,
                'subject' => (string) $message->subject,
                'from_name' => (string) $message->from_name,
                'from_address' => is_string($message->from_address) ? $message->from_address : null,
                'to_name' => (string) $message->to_name,
                'to_address' => is_string($message->to_address) ? $message->to_address : null,
                'reply_to_msgno' => is_numeric($message->reply_to_msgno) ? (int) $message->reply_to_msgno : null,
                'reply_to_external_id' => is_string($message->reply_to_external_id) && $message->reply_to_external_id !== ''
                    ? $message->reply_to_external_id
                    : null,
                'reply1st_msgno' => is_numeric($message->reply1st_msgno) ? (int) $message->reply1st_msgno : null,
                'replynext_msgno' => is_numeric($message->replynext_msgno) ? (int) $message->replynext_msgno : null,
                'source_thread_key' => is_string($message->thread_key) && $message->thread_key !== ''
                    ? $message->thread_key
                    : null,
                'posted_at' => $message->posted_at,
                'arrived_at' => $message->arrived_at,
                'raw_metadata_json' => is_string($message->raw_metadata_json) ? $message->raw_metadata_json : null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function linkThreadMessage(int $threadId, string $canonicalMessageId, int $threadOrder): void
    {
        DB::table('fidonet_thread_messages')->updateOrInsert(
            [
                'fidonet_thread_id' => $threadId,
                'canonical_message_id' => $canonicalMessageId,
            ],
            [
                'thread_order' => $threadOrder,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * @param  FidonetMessageRow  $message
     */
    private function syncParticipants(string $canonicalMessageId, object $message): int
    {
        $count = 0;

        foreach ([
            ['role' => 'from', 'display_name' => (string) $message->from_name, 'address' => $message->from_address],
            ['role' => 'to', 'display_name' => (string) $message->to_name, 'address' => $message->to_address],
        ] as $participantData) {
            $participantId = $this->upsertParticipant(
                $participantData['display_name'],
                is_string($participantData['address']) ? $participantData['address'] : null,
            );

            DB::table('fidonet_message_participants')->updateOrInsert(
                [
                    'canonical_message_id' => $canonicalMessageId,
                    'fidonet_participant_id' => $participantId,
                    'role' => $participantData['role'],
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $count++;
        }

        return $count;
    }

    private function upsertParticipant(string $displayName, ?string $address): int
    {
        $participantKey = sha1(mb_strtolower(trim($displayName)).'|'.mb_strtolower(trim((string) $address)));

        DB::table('fidonet_participants')->updateOrInsert(
            ['participant_key' => $participantKey],
            [
                'display_name' => $displayName,
                'address' => $address,
                'normalized_name' => $this->normalizeIdentity($displayName),
                'normalized_address' => $address !== null ? $this->normalizeIdentity($address) : null,
                'is_odinn_candidate' => $this->isOdinnCandidate($displayName, $address),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('fidonet_participants')
            ->where('participant_key', $participantKey)
            ->value('id');
    }

    /**
     * @param  FidonetMessageRow  $message
     * @return array{
     *     cleaned_authored_text:?string,
     *     quote_text:?string,
     *     metadata_text:?string,
     *     embedded_text:?string,
     *     cleanup_version:string,
     *     is_test_like:bool,
     *     cleanup_notes:?string
     * }
     */
    private function cleanupBody(string $bodyText, string $areaCode, object $message): array
    {
        $authored = [];
        $quotes = [];
        $metadata = [];
        $embedded = [];

        foreach (preg_split('/\r\n|\r|\n/', $bodyText) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $authored[] = '';

                continue;
            }

            if (preg_match('/^[A-Z0-9]{1,4}>/', $trimmed) === 1 || str_starts_with($trimmed, '>')) {
                $quotes[] = $trimmed;

                continue;
            }

            if (str_starts_with($trimmed, '* Origin:')
                || str_starts_with($trimmed, '* Forwarded')
                || preg_match('/^(To|From|Subj|Area|Date)\s*:/i', $trimmed) === 1) {
                $metadata[] = $trimmed;

                continue;
            }

            if (str_contains($trimmed, 'Forwarded by')) {
                $embedded[] = $trimmed;

                continue;
            }

            $authored[] = $trimmed;
        }

        $isTestLike = str_contains(strtoupper($areaCode), 'TEST')
            || str_contains(strtolower((string) $message->from_name), 'test')
            || str_contains(strtolower((string) ($message->from_address ?? '')), 'test')
            || str_contains(strtolower($bodyText), 'test@test');

        return [
            'cleaned_authored_text' => $this->joinSections($authored),
            'quote_text' => $this->joinSections($quotes),
            'metadata_text' => $this->joinSections($metadata),
            'embedded_text' => $this->joinSections($embedded),
            'cleanup_version' => 'v1',
            'is_test_like' => $isTestLike,
            'cleanup_notes' => $isTestLike ? 'flagged as test-like traffic' : null,
        ];
    }

    /**
     * @param  array{cleaned_authored_text:?string,quote_text:?string,metadata_text:?string,embedded_text:?string,cleanup_version:string,is_test_like:bool,cleanup_notes:?string}  $cleanup
     */
    private function upsertCleanup(string $canonicalMessageId, array $cleanup): void
    {
        DB::table('fidonet_message_cleanup')->updateOrInsert(
            ['canonical_message_id' => $canonicalMessageId],
            [
                'cleaned_authored_text' => $cleanup['cleaned_authored_text'],
                'quote_text' => $cleanup['quote_text'],
                'metadata_text' => $cleanup['metadata_text'],
                'embedded_text' => $cleanup['embedded_text'],
                'cleanup_version' => $cleanup['cleanup_version'],
                'is_test_like' => $cleanup['is_test_like'],
                'cleanup_notes' => $cleanup['cleanup_notes'],
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function recordAreaObservation(int $sourceId, string $areaCode): void
    {
        DB::table('fidonet_area_observations')->updateOrInsert(
            [
                'fidonet_source_id' => $sourceId,
                'area_code' => $areaCode,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function recordThreadObservation(int $sourceId, int $threadId): void
    {
        DB::table('fidonet_thread_observations')->updateOrInsert(
            [
                'fidonet_source_id' => $sourceId,
                'fidonet_thread_id' => $threadId,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function recordMessageObservation(int $sourceId, string $canonicalMessageId): void
    {
        DB::table('fidonet_message_observations')->updateOrInsert(
            [
                'fidonet_source_id' => $sourceId,
                'canonical_message_id' => $canonicalMessageId,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function isOdinnCandidate(string $fromName, ?string $fromAddress): bool
    {
        $lowerName = $this->normalizeIdentity($fromName);
        $lowerAddress = $fromAddress !== null ? $this->normalizeIdentity($fromAddress) : '';

        if (str_starts_with($lowerName, 'root@odinn.image.dk')) {
            return false;
        }

        if (str_contains($lowerName, 'diego') && str_contains($lowerName, 'paniodinn')) {
            return false;
        }

        return str_starts_with($lowerName, 'odinn')
            || str_starts_with($lowerName, 'image - odinn')
            || str_starts_with($lowerAddress, 'odinn')
            || str_starts_with($lowerAddress, 'image - odinn');
    }

    private function normalizeIdentity(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    /**
     * @param  array<string, mixed>  $scopeSnapshot
     */
    private function scopeHash(array $scopeSnapshot): string
    {
        return sha1(json_encode($scopeSnapshot, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): ?string => is_string($item) && $item !== '' ? $item : null, $value),
            static fn (?string $item): bool => $item !== null,
        ));
    }

    /**
     * @param  list<string>  $lines
     */
    private function joinSections(array $lines): ?string
    {
        $trimmed = trim(implode("\n", $lines));

        return $trimmed === '' ? null : $trimmed;
    }
}
