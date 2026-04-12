<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  array{
 *     areas?:list<array{
 *         id?:int,
 *         code:string,
 *         name?:string,
 *         source_type?:string|null,
 *         area_type?:string|null
 *     }>,
 *     messages?:list<array{
 *         id?:int,
 *         area_code:string,
 *         msgno:int,
 *         external_id?:string|null,
 *         subject?:string,
 *         from_name?:string,
 *         from_address?:string|null,
 *         to_name?:string,
 *         to_address?:string|null,
 *         body_text?:string,
 *         reply_to_msgno?:int|null,
 *         reply_to_external_id?:string|null,
 *         reply1st_msgno?:int|null,
 *         replynext_msgno?:int|null,
 *         thread_key?:string|null,
 *         posted_at?:string|null,
 *         arrived_at?:string|null,
 *         raw_metadata_json?:string|null
 *     }>
 * } $overrides
 * @return array{root_path:string,env_path:string,database_path:string}
 */
function createFidonetFixtureSource(string $name, array $overrides = []): array
{
    $root = storage_path('framework/testing/fidonet-'.$name.'-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($root);

    $databasePath = $root.'/golded.sqlite';
    $pdo = new PDO('sqlite:'.$databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('
        CREATE TABLE areas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            source_type VARCHAR(255) NULL,
            area_type VARCHAR(255) NULL
        )
    ');

    $pdo->exec('
        CREATE TABLE messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            area_id INTEGER NOT NULL,
            msgno INTEGER NOT NULL,
            external_id VARCHAR(255) NULL,
            subject VARCHAR(255) NOT NULL,
            from_name VARCHAR(255) NOT NULL,
            from_address VARCHAR(255) NULL,
            to_name VARCHAR(255) NOT NULL,
            to_address VARCHAR(255) NULL,
            body_text TEXT NOT NULL,
            reply_to_msgno INTEGER NULL,
            reply_to_external_id VARCHAR(255) NULL,
            reply1st_msgno INTEGER NULL,
            replynext_msgno INTEGER NULL,
            thread_key VARCHAR(255) NULL,
            attributes_raw INTEGER NOT NULL DEFAULT 0,
            posted_at DATETIME NULL,
            arrived_at DATETIME NULL,
            is_read INTEGER NOT NULL DEFAULT 0,
            is_marked INTEGER NOT NULL DEFAULT 0,
            is_bookmarked INTEGER NOT NULL DEFAULT 0,
            raw_metadata_json TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (area_id) REFERENCES areas (id) ON DELETE CASCADE
        )
    ');

    $areas = $overrides['areas'] ?? [[
        'id' => 1,
        'code' => 'WINETDEV',
        'name' => 'WINETDEV',
        'source_type' => 'echomail',
        'area_type' => 'echo',
    ], [
        'id' => 2,
        'code' => 'EMAILTST',
        'name' => 'EMAILTST',
        'source_type' => 'netmail',
        'area_type' => 'mail',
    ]];

    $areaIdsByCode = [];

    foreach ($areas as $area) {
        $statement = $pdo->prepare('
            INSERT INTO areas (id, code, name, source_type, area_type)
            VALUES (:id, :code, :name, :source_type, :area_type)
        ');
        $statement->execute([
            ':id' => $area['id'] ?? null,
            ':code' => $area['code'],
            ':name' => $area['name'] ?? $area['code'],
            ':source_type' => $area['source_type'] ?? null,
            ':area_type' => $area['area_type'] ?? null,
        ]);

        $areaIdsByCode[$area['code']] = (int) $pdo->lastInsertId();

        if (isset($area['id'])) {
            $areaIdsByCode[$area['code']] = $area['id'];
        }
    }

    $messages = $overrides['messages'] ?? [[
        'area_code' => 'WINETDEV',
        'msgno' => 1,
        'external_id' => 'msg-root-1',
        'subject' => 'Delphi 32-bit og WIN32S?',
        'from_name' => 'Odinn Sorensen',
        'from_address' => 'odinn@goldware.dk',
        'to_name' => 'Bo Noergaard',
        'to_address' => '2:236/99',
        'body_text' => "Hej Bo.\n\nVi blev enige om at klienten skulle være 32-bit.\n\n * Origin: Goldware",
        'thread_key' => 'thread-alpha',
        'posted_at' => '1995-09-02 10:00:00',
        'arrived_at' => '1995-09-02 10:05:00',
        'raw_metadata_json' => '{"origin":"Goldware"}',
    ], [
        'area_code' => 'WINETDEV',
        'msgno' => 2,
        'external_id' => 'msg-reply-1',
        'subject' => 'Re: Delphi 32-bit og WIN32S?',
        'from_name' => 'Bo Noergaard',
        'from_address' => '2:236/99',
        'to_name' => 'Odinn Sorensen',
        'to_address' => 'odinn@goldware.dk',
        'body_text' => "Hallojsa Odinn!\n\nOS> Vi blev enige om at klienten skulle være 32-bit.\n\nDet giver mening.",
        'reply_to_msgno' => 1,
        'reply_to_external_id' => 'msg-root-1',
        'thread_key' => 'thread-alpha',
        'posted_at' => '1995-09-02 11:00:00',
        'arrived_at' => '1995-09-02 11:05:00',
        'raw_metadata_json' => '{"reply":"msg-root-1"}',
    ], [
        'area_code' => 'EMAILTST',
        'msgno' => 1,
        'external_id' => 'msg-test-1',
        'subject' => 'test',
        'from_name' => 'odinn@test.test',
        'from_address' => 'odinn@test.test',
        'to_name' => 'test@test.test',
        'to_address' => 'test@test.test',
        'body_text' => "Hello test@test.\n\nOdinn Sorensen",
        'posted_at' => '1995-08-22 00:00:00',
        'arrived_at' => '1995-08-22 00:01:00',
        'raw_metadata_json' => '{"kind":"test"}',
    ], [
        'area_code' => 'WINETDEV',
        'msgno' => 99,
        'external_id' => 'msg-out-of-scope-1',
        'subject' => 'Unrelated',
        'from_name' => 'Someone Else',
        'from_address' => '2:236/55',
        'to_name' => 'Another Person',
        'to_address' => '2:236/56',
        'body_text' => 'Nothing to see here.',
        'thread_key' => 'thread-zeta',
        'posted_at' => '1995-09-04 09:00:00',
        'arrived_at' => '1995-09-04 09:01:00',
        'raw_metadata_json' => '{"kind":"other"}',
    ]];

    foreach ($messages as $message) {
        $statement = $pdo->prepare('
            INSERT INTO messages (
                id,
                area_id,
                msgno,
                external_id,
                subject,
                from_name,
                from_address,
                to_name,
                to_address,
                body_text,
                reply_to_msgno,
                reply_to_external_id,
                reply1st_msgno,
                replynext_msgno,
                thread_key,
                posted_at,
                arrived_at,
                raw_metadata_json,
                created_at,
                updated_at
            ) VALUES (
                :id,
                :area_id,
                :msgno,
                :external_id,
                :subject,
                :from_name,
                :from_address,
                :to_name,
                :to_address,
                :body_text,
                :reply_to_msgno,
                :reply_to_external_id,
                :reply1st_msgno,
                :replynext_msgno,
                :thread_key,
                :posted_at,
                :arrived_at,
                :raw_metadata_json,
                :created_at,
                :updated_at
            )
        ');
        $statement->execute([
            ':id' => $message['id'] ?? null,
            ':area_id' => $areaIdsByCode[$message['area_code']],
            ':msgno' => $message['msgno'],
            ':external_id' => $message['external_id'] ?? null,
            ':subject' => $message['subject'] ?? '',
            ':from_name' => $message['from_name'] ?? 'Unknown',
            ':from_address' => $message['from_address'] ?? null,
            ':to_name' => $message['to_name'] ?? 'Unknown',
            ':to_address' => $message['to_address'] ?? null,
            ':body_text' => $message['body_text'] ?? '',
            ':reply_to_msgno' => $message['reply_to_msgno'] ?? null,
            ':reply_to_external_id' => $message['reply_to_external_id'] ?? null,
            ':reply1st_msgno' => $message['reply1st_msgno'] ?? null,
            ':replynext_msgno' => $message['replynext_msgno'] ?? null,
            ':thread_key' => $message['thread_key'] ?? null,
            ':posted_at' => $message['posted_at'] ?? null,
            ':arrived_at' => $message['arrived_at'] ?? null,
            ':raw_metadata_json' => $message['raw_metadata_json'] ?? null,
            ':created_at' => '2026-04-12 00:00:00',
            ':updated_at' => '2026-04-12 00:00:00',
        ]);
    }

    $envPath = $root.'/.env';
    File::put($envPath, implode("\n", [
        'DB_CONNECTION=sqlite',
        'DB_DATABASE='.$databasePath,
        '',
    ]));

    return [
        'root_path' => $root,
        'env_path' => $envPath,
        'database_path' => $databasePath,
    ];
}
