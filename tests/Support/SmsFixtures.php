<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  array{
 *     messages:list<array{
 *         guid:string,
 *         text:string|null,
 *         is_from_me:int,
 *         handle_id:int|null,
 *         date:int|null,
 *         date_read:int|null,
 *         date_delivered:int|null,
 *         cache_has_attachments:int
 *     }>,
 *     attachments?:list<array{
 *         message_guid:string,
 *         guid:string,
 *         filename:string,
 *         mime_type:string|null,
 *         transfer_name:string|null,
 *         total_bytes:int|null
 *     }>
 * }  $dataset
 * @return array{root_path:string, database_path:string, attachments_root:string}
 */
function createSmsFixtureDatabase(string $name, array $dataset): array
{
    $root = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));
    $attachmentsRoot = $root.'/Attachments';
    $databasePath = $root.'/chat.db';

    File::ensureDirectoryExists($attachmentsRoot.'/00/00');

    $pdo = new PDO('sqlite:'.$databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(<<<'SQL'
        CREATE TABLE chat (
            ROWID INTEGER PRIMARY KEY AUTOINCREMENT,
            guid TEXT,
            chat_identifier TEXT,
            display_name TEXT,
            service_name TEXT,
            style INTEGER,
            is_archived INTEGER,
            room_name TEXT
        );

        CREATE TABLE handle (
            ROWID INTEGER PRIMARY KEY AUTOINCREMENT,
            id TEXT,
            service TEXT,
            uncanonicalized_id TEXT
        );

        CREATE TABLE message (
            ROWID INTEGER PRIMARY KEY AUTOINCREMENT,
            guid TEXT,
            text TEXT,
            attributedBody BLOB,
            is_from_me INTEGER,
            date INTEGER,
            date_read INTEGER,
            date_delivered INTEGER,
            service TEXT,
            is_delivered INTEGER,
            is_read INTEGER,
            is_sent INTEGER,
            cache_has_attachments INTEGER,
            item_type INTEGER,
            associated_message_guid TEXT,
            associated_message_type INTEGER,
            group_title TEXT,
            group_action_type INTEGER,
            handle_id INTEGER
        );

        CREATE TABLE chat_message_join (
            chat_id INTEGER,
            message_id INTEGER
        );

        CREATE TABLE chat_handle_join (
            chat_id INTEGER,
            handle_id INTEGER
        );

        CREATE TABLE attachment (
            ROWID INTEGER PRIMARY KEY AUTOINCREMENT,
            guid TEXT,
            filename TEXT,
            mime_type TEXT,
            transfer_name TEXT,
            total_bytes INTEGER
        );

        CREATE TABLE message_attachment_join (
            message_id INTEGER,
            attachment_id INTEGER
        );
    SQL);

    $pdo->exec(<<<'SQL'
        INSERT INTO chat (ROWID, guid, chat_identifier, display_name, service_name, style, is_archived, room_name)
        VALUES (1, 'chat-guid-001', '+4511111111', NULL, 'iMessage', 45, 0, NULL);

        INSERT INTO handle (ROWID, id, service, uncanonicalized_id)
        VALUES (1, '+4511111111', 'iMessage', '+45 11 11 11 11');

        INSERT INTO chat_handle_join (chat_id, handle_id)
        VALUES (1, 1);
    SQL);

    $insertMessage = $pdo->prepare(<<<'SQL'
        INSERT INTO message (
            guid, text, attributedBody, is_from_me, date, date_read, date_delivered,
            service, is_delivered, is_read, is_sent, cache_has_attachments, item_type,
            associated_message_guid, associated_message_type, group_title, group_action_type, handle_id
        ) VALUES (
            :guid, :text, NULL, :is_from_me, :date, :date_read, :date_delivered,
            'iMessage', 1, 1, 1, :cache_has_attachments, 0,
            NULL, NULL, NULL, NULL, :handle_id
        )
    SQL);

    $insertChatMessageJoin = $pdo->prepare('INSERT INTO chat_message_join (chat_id, message_id) VALUES (1, :message_id)');
    $messageRowIds = [];

    foreach ($dataset['messages'] as $message) {
        $insertMessage->execute([
            'guid' => $message['guid'],
            'text' => $message['text'],
            'is_from_me' => $message['is_from_me'],
            'date' => $message['date'],
            'date_read' => $message['date_read'],
            'date_delivered' => $message['date_delivered'],
            'cache_has_attachments' => $message['cache_has_attachments'],
            'handle_id' => $message['handle_id'],
        ]);

        $messageRowId = (int) $pdo->lastInsertId();
        $messageRowIds[$message['guid']] = $messageRowId;
        $insertChatMessageJoin->execute([
            'message_id' => $messageRowId,
        ]);
    }

    if (($dataset['attachments'] ?? []) !== []) {
        $insertAttachment = $pdo->prepare(<<<'SQL'
            INSERT INTO attachment (guid, filename, mime_type, transfer_name, total_bytes)
            VALUES (:guid, :filename, :mime_type, :transfer_name, :total_bytes)
        SQL);
        $insertMessageAttachmentJoin = $pdo->prepare(
            'INSERT INTO message_attachment_join (message_id, attachment_id) VALUES (:message_id, :attachment_id)'
        );

        foreach ($dataset['attachments'] as $attachment) {
            File::put($attachmentsRoot.'/00/00/sample.jpg', 'binary-ish');

            $insertAttachment->execute([
                'guid' => $attachment['guid'],
                'filename' => $attachment['filename'],
                'mime_type' => $attachment['mime_type'],
                'transfer_name' => $attachment['transfer_name'],
                'total_bytes' => $attachment['total_bytes'],
            ]);

            $insertMessageAttachmentJoin->execute([
                'message_id' => $messageRowIds[$attachment['message_guid']],
                'attachment_id' => (int) $pdo->lastInsertId(),
            ]);
        }
    }

    return [
        'root_path' => $root,
        'database_path' => $databasePath,
        'attachments_root' => $attachmentsRoot,
    ];
}

function appleTimestampForUnix(int $unixTimestamp): int
{
    return ($unixTimestamp - 978307200) * 1_000_000_000;
}

/**
 * @param  list<array{
 *     first_name:?string,
 *     last_name:?string,
 *     organization:?string,
 *     phones?:list<string>,
 *     emails?:list<string>
 * }>  $contacts
 */
function createAddressBookFixtureDatabase(string $name, array $contacts): string
{
    $root = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));
    $databasePath = $root.'/AddressBook-v22.abcddb';

    File::ensureDirectoryExists($root);

    $pdo = new PDO('sqlite:'.$databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(<<<'SQL'
        CREATE TABLE ZABCDRECORD (
            Z_PK INTEGER PRIMARY KEY AUTOINCREMENT,
            ZFIRSTNAME TEXT,
            ZLASTNAME TEXT,
            ZORGANIZATION TEXT
        );

        CREATE TABLE ZABCDPHONENUMBER (
            Z_PK INTEGER PRIMARY KEY AUTOINCREMENT,
            ZOWNER INTEGER,
            ZFULLNUMBER TEXT
        );

        CREATE TABLE ZABCDEMAILADDRESS (
            Z_PK INTEGER PRIMARY KEY AUTOINCREMENT,
            ZOWNER INTEGER,
            ZADDRESS TEXT
        );
    SQL);

    $insertRecord = $pdo->prepare(<<<'SQL'
        INSERT INTO ZABCDRECORD (ZFIRSTNAME, ZLASTNAME, ZORGANIZATION)
        VALUES (:first_name, :last_name, :organization)
    SQL);
    $insertPhone = $pdo->prepare('INSERT INTO ZABCDPHONENUMBER (ZOWNER, ZFULLNUMBER) VALUES (:owner, :full_number)');
    $insertEmail = $pdo->prepare('INSERT INTO ZABCDEMAILADDRESS (ZOWNER, ZADDRESS) VALUES (:owner, :address)');

    foreach ($contacts as $contact) {
        $insertRecord->execute([
            'first_name' => $contact['first_name'],
            'last_name' => $contact['last_name'],
            'organization' => $contact['organization'],
        ]);

        $ownerId = (int) $pdo->lastInsertId();

        foreach ($contact['phones'] ?? [] as $phone) {
            $insertPhone->execute([
                'owner' => $ownerId,
                'full_number' => $phone,
            ]);
        }

        foreach ($contact['emails'] ?? [] as $email) {
            $insertEmail->execute([
                'owner' => $ownerId,
                'address' => $email,
            ]);
        }
    }

    return $databasePath;
}
