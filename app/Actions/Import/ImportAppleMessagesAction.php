<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\AppleMessagesImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PDO;
use PDOStatement;

class ImportAppleMessagesAction
{
    private const array REQUIRED_SQLITE_TABLES = [
        'attachment',
        'chat',
        'chat_handle_join',
        'chat_message_join',
        'handle',
        'message',
        'message_attachment_join',
    ];

    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly SourceObservationStore $sourceObservationStore,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): AppleMessagesImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'apple-messages-import',
            import: fn (Run $run): array => $this->importDatabase($dispatchPayload, $run, $progress),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->writeArtifacts($run, $dispatchPayload, $summary);
            },
        );
        /** @var array{
         *     run:Run,
         *     summary:array{
         *         source_file:string,
         *         source_set_id:int,
         *         conversations:int,
         *         participants:int,
         *         messages:int,
         *         attachments:int,
         *         inserted_messages:int,
         *         reobserved_messages:int
         *     }
         * } $execution
         */

        return new AppleMessagesImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array{
     *     source_file:string,
     *     source_set_id:int,
     *     conversations:int,
     *     participants:int,
     *     messages:int,
     *     attachments:int,
     *     inserted_messages:int,
     *     reobserved_messages:int
     * }
     */
    private function importDatabase(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        $databasePath = $this->resolveDatabasePath($dispatchPayload);
        $attachmentsRoot = $this->resolveAttachmentsRoot($dispatchPayload, $databasePath);
        $contactMap = $this->buildContactMap($dispatchPayload);
        $sqlite = $this->connectToSourceDatabase($databasePath);

        $this->assertRequiredTables($sqlite);

        $sourceSetId = DB::transaction(function () use ($dispatchPayload, $databasePath, $attachmentsRoot): int {
            return $this->sourceObservationStore->upsertAndReturnId(
                table: 'apple_messages_source_sets',
                unique: [
                    'source_key' => sha1($databasePath),
                ],
                values: [
                    'source_locator' => $databasePath,
                    'access_mode' => $dispatchPayload->accessMode,
                    'attachments_root' => $attachmentsRoot,
                ],
            );
        });

        $attachmentsByMessage = $this->loadAttachmentsByMessage($sqlite);
        $participantsByChat = $this->loadParticipantsByChat($sqlite);
        $chats = $this->loadChats($sqlite);

        $summary = [
            'source_file' => basename($databasePath),
            'source_set_id' => $sourceSetId,
            'conversations' => 0,
            'participants' => 0,
            'messages' => 0,
            'attachments' => 0,
            'inserted_messages' => 0,
            'reobserved_messages' => 0,
        ];

        $observedParticipantIds = [];

        DB::transaction(function () use (
            $attachmentsByMessage,
            $attachmentsRoot,
            $chats,
            $contactMap,
            $dispatchPayload,
            $participantsByChat,
            $progress,
            $run,
            $sourceSetId,
            &$observedParticipantIds,
            &$summary,
            $sqlite
        ): void {
            $this->reportProgress($progress, 'chats_resolved', [
                'total_chats' => count($chats),
            ]);

            foreach ($chats as $index => $chat) {
                $conversationId = $this->upsertConversation($chat);
                $summary['conversations']++;

                foreach ($participantsByChat[$chat['row_id']] ?? [] as $participant) {
                    $participantId = $this->upsertParticipant(
                        $participant,
                        $this->resolveDisplayName($contactMap, $participant['identifier']),
                    );
                    $observedParticipantIds[$participantId] = true;

                    $this->sourceObservationStore->record(
                        table: 'apple_messages_conversation_participant',
                        unique: [
                            'apple_messages_conversation_id' => $conversationId,
                            'apple_messages_participant_id' => $participantId,
                        ],
                    );
                }

                $messages = $this->loadMessagesForChat($sqlite, $chat['row_id']);

                foreach ($messages as $message) {
                    $senderParticipantId = $this->resolveSenderParticipantId($message, $contactMap);

                    if ($senderParticipantId !== null) {
                        $observedParticipantIds[$senderParticipantId] = true;
                    }

                    $messageRow = $this->upsertMessage($conversationId, $senderParticipantId, $message);

                    if ($messageRow['wasRecentlyCreated']) {
                        $summary['inserted_messages']++;
                    } else {
                        $summary['reobserved_messages']++;
                    }

                    $this->sourceObservationStore->record(
                        table: 'apple_messages_message_observations',
                        unique: [
                            'apple_messages_message_id' => $messageRow['id'],
                            'apple_messages_source_set_id' => $sourceSetId,
                        ],
                        values: [
                            'source_message_row_id' => $message['row_id'],
                        ],
                    );

                    $summary['messages']++;

                    foreach ($attachmentsByMessage[$message['row_id']] ?? [] as $attachment) {
                        $this->upsertAttachment($messageRow['id'], $attachment, $attachmentsRoot);
                        $summary['attachments']++;
                    }

                    $this->provenanceWriter->link(new WriteProvenanceLinkData(
                        runId: $run->id,
                        outputTarget: 'apple_messages_messages:'.$messageRow['id'],
                        claimKey: 'imported-message',
                        evidenceType: 'source-file',
                        evidenceRef: basename($dispatchPayload->sourceLocator).'#message:'.($message['guid'] ?? $message['row_id']),
                    ));
                }

                $this->reportProgress($progress, 'chat_completed', [
                    'chat' => $chat['chat_identifier'] ?? $chat['source_guid'] ?? 'unknown-chat',
                    'current_chat' => $index + 1,
                    'total_chats' => count($chats),
                    'messages' => $summary['messages'],
                ]);
            }
        });

        $summary['participants'] = count($observedParticipantIds);

        return $summary;
    }

    private function resolveDatabasePath(ImporterDispatchData $dispatchPayload): string
    {
        if ($dispatchPayload->accessMode === 'archive') {
            return $dispatchPayload->sourceLocator;
        }

        $databasePath = rtrim($dispatchPayload->sourceLocator, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'chat.db';

        if (! File::exists($databasePath) || ! File::isFile($databasePath)) {
            throw new InvalidArgumentException('Malformed Apple Messages source payload: chat.db was not found inside the requested directory.');
        }

        return $databasePath;
    }

    private function resolveAttachmentsRoot(ImporterDispatchData $dispatchPayload, string $databasePath): string
    {
        $attachmentsRoot = $dispatchPayload->scopeSnapshot['attachments_root'] ?? dirname($databasePath).DIRECTORY_SEPARATOR.'Attachments';

        if (! is_string($attachmentsRoot) || $attachmentsRoot === '') {
            return dirname($databasePath).DIRECTORY_SEPARATOR.'Attachments';
        }

        $normalizedPath = realpath($attachmentsRoot);

        return $normalizedPath !== false ? $normalizedPath : $attachmentsRoot;
    }

    /**
     * @return array<string, string>
     */
    private function buildContactMap(ImporterDispatchData $dispatchPayload): array
    {
        $contactMap = [];

        foreach ($this->resolveContactDatabasePaths($dispatchPayload) as $databasePath) {
            $sqlite = $this->connectToAddressBookDatabase($databasePath);

            foreach ($this->loadPhoneContactRows($sqlite) as $row) {
                $displayName = $this->displayName(
                    $row['first_name'] ?? null,
                    $row['last_name'] ?? null,
                    $row['organization'] ?? null,
                );

                if ($displayName === null) {
                    continue;
                }

                $normalizedPhone = $this->normalizePhoneIdentifier($row['full_number'] ?? null);

                if ($normalizedPhone !== null && ! array_key_exists($normalizedPhone, $contactMap)) {
                    $contactMap[$normalizedPhone] = $displayName;
                }
            }

            foreach ($this->loadEmailContactRows($sqlite) as $row) {
                $displayName = $this->displayName(
                    $row['first_name'] ?? null,
                    $row['last_name'] ?? null,
                    $row['organization'] ?? null,
                );

                if ($displayName === null) {
                    continue;
                }

                $normalizedEmail = $this->normalizeEmailIdentifier($row['address'] ?? null);

                if ($normalizedEmail !== null && ! array_key_exists($normalizedEmail, $contactMap)) {
                    $contactMap[$normalizedEmail] = $displayName;
                }
            }
        }

        return $contactMap;
    }

    /**
     * @return list<string>
     */
    private function resolveContactDatabasePaths(ImporterDispatchData $dispatchPayload): array
    {
        $paths = $dispatchPayload->scopeSnapshot['contacts_databases'] ?? null;

        if (! is_array($paths)) {
            return [];
        }

        $resolvedPaths = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }
            if ($path === '') {
                continue;
            }
            $resolvedPath = realpath($path);
            $resolvedPaths[] = $resolvedPath !== false ? $resolvedPath : $path;
        }

        return array_values(array_unique(array_filter($resolvedPaths, is_file(...))));
    }

    private function connectToAddressBookDatabase(string $databasePath): PDO
    {
        $sqlite = new PDO('sqlite:'.$databasePath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $sqlite;
    }

    /**
     * @return list<array{first_name:mixed,last_name:mixed,organization:mixed,full_number:mixed}>
     */
    private function loadPhoneContactRows(PDO $sqlite): array
    {
        if (! $this->hasAddressBookTables($sqlite, ['ZABCDRECORD', 'ZABCDPHONENUMBER'])) {
            return [];
        }

        return array_values($this->queryOrFail($sqlite, <<<'SQL'
            SELECT
                r.ZFIRSTNAME AS first_name,
                r.ZLASTNAME AS last_name,
                r.ZORGANIZATION AS organization,
                p.ZFULLNUMBER AS full_number
            FROM ZABCDRECORD r
            JOIN ZABCDPHONENUMBER p ON p.ZOWNER = r.Z_PK
            WHERE p.ZFULLNUMBER IS NOT NULL
        SQL)->fetchAll());
    }

    /**
     * @return list<array{first_name:mixed,last_name:mixed,organization:mixed,address:mixed}>
     */
    private function loadEmailContactRows(PDO $sqlite): array
    {
        if (! $this->hasAddressBookTables($sqlite, ['ZABCDRECORD', 'ZABCDEMAILADDRESS'])) {
            return [];
        }

        return array_values($this->queryOrFail($sqlite, <<<'SQL'
            SELECT
                r.ZFIRSTNAME AS first_name,
                r.ZLASTNAME AS last_name,
                r.ZORGANIZATION AS organization,
                e.ZADDRESS AS address
            FROM ZABCDRECORD r
            JOIN ZABCDEMAILADDRESS e ON e.ZOWNER = r.Z_PK
            WHERE e.ZADDRESS IS NOT NULL
        SQL)->fetchAll());
    }

    /**
     * @param  list<string>  $tables
     */
    private function hasAddressBookTables(PDO $sqlite, array $tables): bool
    {
        $existingTables = $this->queryOrFail(
            $sqlite,
            "SELECT name FROM sqlite_master WHERE type = 'table'",
        )->fetchAll();

        $tableNames = array_map(
            static fn (array $row): string => (string) $row['name'],
            $existingTables,
        );

        return array_diff($tables, $tableNames) === [];
    }

    private function displayName(mixed $firstName, mixed $lastName, mixed $organization): ?string
    {
        $parts = array_values(array_filter([
            $this->nullableString($firstName),
            $this->nullableString($lastName),
        ]));

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return $this->nullableString($organization);
    }

    /**
     * @param  array<string, string>  $contactMap
     */
    private function resolveDisplayName(array $contactMap, string $identifier): ?string
    {
        if (str_contains($identifier, '@')) {
            $normalizedEmail = $this->normalizeEmailIdentifier($identifier);

            return $normalizedEmail === null ? null : ($contactMap[$normalizedEmail] ?? null);
        }

        $normalizedPhone = $this->normalizePhoneIdentifier($identifier);

        return $normalizedPhone === null ? null : ($contactMap[$normalizedPhone] ?? null);
    }

    private function normalizePhoneIdentifier(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0045')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 8) {
            $digits = '45'.$digits;
        }

        return $digits;
    }

    private function normalizeEmailIdentifier(mixed $value): ?string
    {
        $email = $this->nullableString($value);

        return $email === null ? null : strtolower($email);
    }

    private function connectToSourceDatabase(string $databasePath): PDO
    {
        if (! File::exists($databasePath) || ! File::isFile($databasePath)) {
            throw new InvalidArgumentException('Malformed Apple Messages source payload: chat.db is not reachable.');
        }

        $sqlite = new PDO('sqlite:'.$databasePath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $sqlite;
    }

    private function assertRequiredTables(PDO $sqlite): void
    {
        $rows = $this->queryOrFail(
            $sqlite,
            "SELECT name FROM sqlite_master WHERE type = 'table'",
        )->fetchAll();

        $tables = array_map(
            static fn (array $row): string => (string) $row['name'],
            $rows,
        );

        $missing = array_values(array_diff(self::REQUIRED_SQLITE_TABLES, $tables));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Malformed Apple Messages source payload: required SQLite tables are missing ('.implode(', ', $missing).').'
            );
        }
    }

    /**
     * @return list<array{
     *     row_id:int,
     *     source_guid:?string,
     *     chat_identifier:?string,
     *     display_name:?string,
     *     room_name:?string,
     *     service:?string,
     *     style:?int,
     *     is_archived:bool
     * }>
     */
    private function loadChats(PDO $sqlite): array
    {
        $rows = $this->queryOrFail($sqlite, <<<'SQL'
            SELECT
                ROWID AS row_id,
                guid,
                chat_identifier,
                display_name,
                room_name,
                service_name,
                style,
                is_archived
            FROM chat
            ORDER BY ROWID
        SQL)->fetchAll();

        return array_values(array_map(function (array $row): array {
            return [
                'row_id' => (int) $row['row_id'],
                'source_guid' => $this->nullableString($row['guid'] ?? null),
                'chat_identifier' => $this->nullableString($row['chat_identifier'] ?? null),
                'display_name' => $this->nullableString($row['display_name'] ?? null),
                'room_name' => $this->nullableString($row['room_name'] ?? null),
                'service' => $this->nullableString($row['service_name'] ?? null),
                'style' => isset($row['style']) ? (int) $row['style'] : null,
                'is_archived' => (bool) ($row['is_archived'] ?? false),
            ];
        }, $rows));
    }

    /**
     * @return array<int, list<array{identifier:string, service:?string, uncanonicalized_identifier:?string}>>
     */
    private function loadParticipantsByChat(PDO $sqlite): array
    {
        $rows = $this->queryOrFail($sqlite, <<<'SQL'
            SELECT
                chj.chat_id,
                h.id AS identifier,
                h.service,
                h.uncanonicalized_id
            FROM chat_handle_join chj
            JOIN handle h ON h.ROWID = chj.handle_id
            ORDER BY chj.chat_id, h.ROWID
        SQL)->fetchAll();

        $participantsByChat = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! is_string($row['identifier'] ?? null)) {
                continue;
            }
            if ($row['identifier'] === '') {
                continue;
            }
            $participantsByChat[(int) $row['chat_id']][] = [
                'identifier' => $row['identifier'],
                'service' => $this->nullableString($row['service'] ?? null),
                'uncanonicalized_identifier' => $this->nullableString($row['uncanonicalized_id'] ?? null),
            ];
        }

        return $participantsByChat;
    }

    /**
     * @return array<int, list<array{
     *     source_guid:?string,
     *     source_filename:?string,
     *     mime_type:?string,
     *     transfer_name:?string,
     *     total_bytes:?int
     * }>>
     */
    private function loadAttachmentsByMessage(PDO $sqlite): array
    {
        $rows = $this->queryOrFail($sqlite, <<<'SQL'
            SELECT
                maj.message_id,
                a.guid,
                a.filename,
                a.mime_type,
                a.transfer_name,
                a.total_bytes
            FROM message_attachment_join maj
            JOIN attachment a ON a.ROWID = maj.attachment_id
        SQL)->fetchAll();

        $attachmentsByMessage = [];

        foreach ($rows as $row) {
            $attachmentsByMessage[(int) $row['message_id']][] = [
                'source_guid' => $this->nullableString($row['guid'] ?? null),
                'source_filename' => $this->nullableString($row['filename'] ?? null),
                'mime_type' => $this->nullableString($row['mime_type'] ?? null),
                'transfer_name' => $this->nullableString($row['transfer_name'] ?? null),
                'total_bytes' => $this->normalizeAttachmentBytes($row['total_bytes'] ?? null),
            ];
        }

        return $attachmentsByMessage;
    }

    /**
     * @return list<array{
     *     row_id:int,
     *     guid:?string,
     *     text:?string,
     *     is_from_me:bool,
     *     date:?string,
     *     date_read:?string,
     *     date_delivered:?string,
     *     service:?string,
     *     is_delivered:bool,
     *     is_read:bool,
     *     is_sent:bool,
     *     item_type:int,
     *     group_title:?string,
     *     group_action_type:?int,
     *     reaction_to_guid:?string,
     *     reaction_type:?int,
     *     sender_identifier:?string,
     *     sender_service:?string,
     *     sender_uncanonicalized_identifier:?string
     * }>
     */
    private function loadMessagesForChat(PDO $sqlite, int $chatRowId): array
    {
        $statement = $sqlite->prepare(<<<'SQL'
            SELECT
                m.ROWID AS row_id,
                m.guid,
                m.text,
                m.attributedBody,
                m.is_from_me,
                m.date,
                m.date_read,
                m.date_delivered,
                m.service,
                m.is_delivered,
                m.is_read,
                m.is_sent,
                m.item_type,
                m.group_title,
                m.group_action_type,
                m.associated_message_guid,
                m.associated_message_type,
                h.id AS sender_identifier,
                h.service AS sender_service,
                h.uncanonicalized_id AS sender_uncanonicalized_identifier
            FROM chat_message_join cmj
            JOIN message m ON m.ROWID = cmj.message_id
            LEFT JOIN handle h ON h.ROWID = m.handle_id
            WHERE cmj.chat_id = :chat_id
            ORDER BY m.date ASC, m.ROWID ASC
        SQL);

        $statement->execute([
            'chat_id' => $chatRowId,
        ]);

        $rows = $statement->fetchAll();

        return array_values(array_map(function (array $row): array {
            return [
                'row_id' => (int) $row['row_id'],
                'guid' => $this->nullableString($row['guid'] ?? null),
                'text' => $this->extractMessageText($row['text'] ?? null, $row['attributedBody'] ?? null),
                'is_from_me' => (bool) ($row['is_from_me'] ?? false),
                'date' => $this->macTimestampToDateTime($row['date'] ?? null),
                'date_read' => $this->macTimestampToDateTime($row['date_read'] ?? null),
                'date_delivered' => $this->macTimestampToDateTime($row['date_delivered'] ?? null),
                'service' => $this->nullableString($row['service'] ?? null),
                'is_delivered' => (bool) ($row['is_delivered'] ?? false),
                'is_read' => (bool) ($row['is_read'] ?? false),
                'is_sent' => (bool) ($row['is_sent'] ?? false),
                'item_type' => isset($row['item_type']) ? (int) $row['item_type'] : 0,
                'group_title' => $this->nullableString($row['group_title'] ?? null),
                'group_action_type' => isset($row['group_action_type']) ? (int) $row['group_action_type'] : null,
                'reaction_to_guid' => $this->nullableString($row['associated_message_guid'] ?? null),
                'reaction_type' => isset($row['associated_message_type']) ? (int) $row['associated_message_type'] : null,
                'sender_identifier' => $this->nullableString($row['sender_identifier'] ?? null),
                'sender_service' => $this->nullableString($row['sender_service'] ?? null),
                'sender_uncanonicalized_identifier' => $this->nullableString($row['sender_uncanonicalized_identifier'] ?? null),
            ];
        }, $rows));
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    private function upsertConversation(array $chat): int
    {
        $conversationKey = $this->conversationKey($chat);

        DB::table('apple_messages_conversations')->updateOrInsert(
            [
                'conversation_key' => $conversationKey,
            ],
            [
                'source_guid' => $chat['source_guid'],
                'chat_identifier' => $chat['chat_identifier'],
                'display_name' => $chat['display_name'],
                'room_name' => $chat['room_name'],
                'service' => $chat['service'],
                'style' => $chat['style'],
                'is_archived' => $chat['is_archived'],
                'raw_chat' => json_encode($chat, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('apple_messages_conversations')
            ->where('conversation_key', $conversationKey)
            ->value('id');
    }

    /**
     * @param  array{identifier:string, service:?string, uncanonicalized_identifier:?string}  $participant
     */
    private function upsertParticipant(array $participant, ?string $displayName = null): int
    {
        $existingParticipant = DB::table('apple_messages_participants')
            ->where('identifier', $participant['identifier'])
            ->first();

        DB::table('apple_messages_participants')->updateOrInsert(
            [
                'identifier' => $participant['identifier'],
            ],
            [
                'service' => $participant['service'],
                'uncanonicalized_identifier' => $participant['uncanonicalized_identifier'],
                'display_name' => $existingParticipant === null ? $displayName : $existingParticipant->display_name,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('apple_messages_participants')
            ->where('identifier', $participant['identifier'])
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{id:int, wasRecentlyCreated:bool}
     */
    private function upsertMessage(int $conversationId, ?int $senderParticipantId, array $message): array
    {
        $canonicalKey = $this->messageCanonicalKey($message);
        $existing = DB::table('apple_messages_messages')
            ->where('canonical_key', $canonicalKey)
            ->first();

        $existingConversationId = $existing === null ? null : (int) $existing->apple_messages_conversation_id;
        $existingSenderParticipantId = $existing === null || $existing->sender_participant_id === null
            ? null
            : (int) $existing->sender_participant_id;
        $existingSourceGuid = $existing === null ? null : $this->nullableString($existing->source_guid);
        $existingSourceRowId = $existing === null || $existing->source_row_id === null ? null : (int) $existing->source_row_id;
        $existingSentAt = $existing === null ? null : $this->nullableString($existing->sent_at);
        $existingReadAt = $existing === null ? null : $this->nullableString($existing->read_at);
        $existingDeliveredAt = $existing === null ? null : $this->nullableString($existing->delivered_at);
        $existingService = $existing === null ? null : $this->nullableString($existing->service);
        $existingTextBody = $existing === null ? null : $this->nullableString($existing->text_body);
        $existingGroupTitle = $existing === null ? null : $this->nullableString($existing->group_title);
        $existingReactionToGuid = $existing === null ? null : $this->nullableString($existing->reaction_to_guid);
        $existingItemType = $existing === null || $existing->item_type === null ? null : (int) $existing->item_type;
        $existingGroupActionType = $existing === null || $existing->group_action_type === null ? null : (int) $existing->group_action_type;
        $existingReactionType = $existing === null || $existing->reaction_type === null ? null : (int) $existing->reaction_type;

        $payload = [
            'apple_messages_conversation_id' => $existingConversationId ?? $conversationId,
            'sender_participant_id' => $existingSenderParticipantId ?? $senderParticipantId,
            'source_guid' => $existingSourceGuid ?? $message['guid'],
            'source_row_id' => $existingSourceRowId ?? $message['row_id'],
            'sent_at' => $this->pickTimestamp($existingSentAt, $message['date']),
            'read_at' => $this->pickTimestamp($existingReadAt, $message['date_read']),
            'delivered_at' => $this->pickTimestamp($existingDeliveredAt, $message['date_delivered']),
            'from_me' => $existing === null ? $message['is_from_me'] : (bool) $existing->from_me,
            'service' => $existingService ?: $message['service'],
            'text_body' => $existingTextBody ?: $message['text'],
            'is_delivered' => $existing === null ? $message['is_delivered'] : ((bool) $existing->is_delivered || $message['is_delivered']),
            'is_read' => $existing === null ? $message['is_read'] : ((bool) $existing->is_read || $message['is_read']),
            'is_sent' => $existing === null ? $message['is_sent'] : ((bool) $existing->is_sent || $message['is_sent']),
            'item_type' => $existingItemType ?? $message['item_type'],
            'group_title' => $existingGroupTitle ?: $message['group_title'],
            'group_action_type' => $existingGroupActionType ?? $message['group_action_type'],
            'reaction_to_guid' => $existingReactionToGuid ?: $message['reaction_to_guid'],
            'reaction_type' => $existingReactionType ?? $message['reaction_type'],
            'raw_message' => json_encode($message, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
            'created_at' => now(),
        ];

        DB::table('apple_messages_messages')->updateOrInsert(
            [
                'canonical_key' => $canonicalKey,
            ],
            $payload,
        );

        $messageId = (int) DB::table('apple_messages_messages')
            ->where('canonical_key', $canonicalKey)
            ->value('id');

        return [
            'id' => $messageId,
            'wasRecentlyCreated' => $existing === null,
        ];
    }

    /**
     * @param  array{
     *     source_guid:?string,
     *     source_filename:?string,
     *     mime_type:?string,
     *     transfer_name:?string,
     *     total_bytes:?int
     * }  $attachment
     */
    private function upsertAttachment(int $messageId, array $attachment, string $attachmentsRoot): void
    {
        $relativePath = $this->relativeAttachmentPath($attachment['source_filename'], $attachmentsRoot);
        $attachmentKey = $attachment['source_guid']
            ?? sha1(implode('|', [
                (string) $messageId,
                (string) $attachment['source_filename'],
                (string) $attachment['transfer_name'],
            ]));

        DB::table('apple_messages_attachments')->updateOrInsert(
            [
                'attachment_key' => $attachmentKey,
            ],
            [
                'apple_messages_message_id' => $messageId,
                'source_guid' => $attachment['source_guid'],
                'relative_path' => $relativePath,
                'source_filename' => $attachment['source_filename'],
                'mime_type' => $attachment['mime_type'],
                'transfer_name' => $attachment['transfer_name'],
                'total_bytes' => $attachment['total_bytes'],
                'raw_attachment' => json_encode($attachment, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * @param  array{is_from_me:bool, sender_identifier:?string, sender_service:?string, sender_uncanonicalized_identifier:?string}  $message
     * @param  array<string, string>  $contactMap
     */
    private function resolveSenderParticipantId(array $message, array $contactMap): ?int
    {
        if ($message['is_from_me'] || ! is_string($message['sender_identifier'] ?? null) || $message['sender_identifier'] === '') {
            return null;
        }

        return $this->upsertParticipant([
            'identifier' => $message['sender_identifier'],
            'service' => $message['sender_service'],
            'uncanonicalized_identifier' => $message['sender_uncanonicalized_identifier'],
        ], $this->resolveDisplayName($contactMap, $message['sender_identifier']));
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    private function conversationKey(array $chat): string
    {
        if (is_string($chat['source_guid'] ?? null) && $chat['source_guid'] !== '') {
            return 'guid:'.$chat['source_guid'];
        }

        return 'derived:'.sha1(implode('|', [
            (string) ($chat['chat_identifier'] ?? ''),
            (string) ($chat['service'] ?? ''),
            (string) ($chat['style'] ?? ''),
        ]));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function messageCanonicalKey(array $message): string
    {
        if (is_string($message['guid'] ?? null) && $message['guid'] !== '') {
            return 'guid:'.$message['guid'];
        }

        return 'derived:'.sha1(json_encode([
            'sender_identifier' => $message['sender_identifier'],
            'text' => $message['text'],
            'from_me' => $message['is_from_me'],
            'date' => $message['date'],
            'service' => $message['service'],
            'item_type' => $message['item_type'],
        ], JSON_THROW_ON_ERROR));
    }

    private function macTimestampToDateTime(mixed $timestamp): ?string
    {
        if ($timestamp === null || $timestamp === '' || ! is_numeric($timestamp)) {
            return null;
        }

        $unixTimestamp = ((int) $timestamp / 1_000_000_000) + 978307200;
        $dateTime = new DateTimeImmutable('@'.$unixTimestamp)->setTimezone(new DateTimeZone('UTC'));

        return $dateTime->format('Y-m-d H:i:s');
    }

    private function extractMessageText(mixed $text, mixed $attributedBody): ?string
    {
        if (is_string($text) && $text !== '') {
            return $text;
        }

        if (! is_string($attributedBody)) {
            return null;
        }

        $marker = "\x84\x01+";
        $position = strpos($attributedBody, $marker);

        if ($position === false) {
            return null;
        }

        $position += 3;
        $byte = ord($attributedBody[$position]);

        if ($byte === 0x81) {
            $stringLength = ord($attributedBody[$position + 1]) + (ord($attributedBody[$position + 2]) * 256);
            $textStart = $position + 3;
        } elseif ($byte < 0x80) {
            $stringLength = $byte;
            $textStart = $position + 1;
        } else {
            return null;
        }

        $raw = substr($attributedBody, $textStart, $stringLength);

        if ($raw === '') {
            return null;
        }

        return mb_check_encoding($raw, 'UTF-8')
            ? $raw
            : mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    }

    private function relativeAttachmentPath(?string $sourceFilename, string $attachmentsRoot): ?string
    {
        if ($sourceFilename === null || $sourceFilename === '') {
            return null;
        }

        $normalizedFilename = str_replace('\\', '/', $sourceFilename);
        $normalizedRoot = str_replace('\\', '/', rtrim($attachmentsRoot, DIRECTORY_SEPARATOR));

        if (str_starts_with($normalizedFilename, '~/Library/Messages/Attachments/')) {
            return 'Attachments/'.ltrim(substr($normalizedFilename, strlen('~/Library/Messages/Attachments/')), '/');
        }

        if (str_starts_with($normalizedFilename, $normalizedRoot.'/')) {
            return 'Attachments/'.ltrim(substr($normalizedFilename, strlen($normalizedRoot) + 1), '/');
        }

        return basename($normalizedFilename);
    }

    private function pickTimestamp(?string $existing, ?string $incoming): ?string
    {
        return $existing ?? $incoming;
    }

    private function normalizeAttachmentBytes(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $bytes = (int) $value;

        return $bytes < 0 ? null : $bytes;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function queryOrFail(PDO $sqlite, string $query): PDOStatement
    {
        $statement = $sqlite->query($query);

        if ($statement === false) {
            throw new InvalidArgumentException('Malformed Apple Messages source payload: SQLite query failed.');
        }

        return $statement;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function writeArtifacts(Run $run, ImporterDispatchData $dispatchPayload, array $summary): void
    {
        $this->importArtifactWriter->write(
            run: $run,
            dispatchPayload: $dispatchPayload,
            sourceType: 'apple-messages',
            artifactKind: 'apple-messages-import-summary',
            summary: $summary,
        );
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
