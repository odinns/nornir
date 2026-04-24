<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  list<array{
 *     volume_label: string,
 *     mount_path_last_seen?: string|null,
 *     directories: list<array{
 *         full_path: string,
 *         files: list<array{
 *             basename: string,
 *             extension?: string|null,
 *             normalized_file_type: string,
 *             size_bytes?: int|null,
 *             fs_created_at?: string|null,
 *             fs_modified_at?: string|null,
 *             duplicate_key?: string|null,
 *         }>
 *     }>
 * }>  $volumes
 * @return array{connection_name: string, database_path: string, env_path: string}
 */
function createMoniqueFixtureDatabase(string $name, array $volumes): array
{
    $root = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($root);

    $databasePath = $root.'/monique.db';

    $pdo = new PDO('sqlite:'.$databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(<<<'SQL'
        CREATE TABLE volumes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL,
            mount_path_last_seen TEXT
        );

        CREATE TABLE directories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            volume_id INTEGER NOT NULL,
            full_path TEXT NOT NULL,
            FOREIGN KEY (volume_id) REFERENCES volumes(id)
        );

        CREATE TABLE files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            directory_id INTEGER NOT NULL,
            basename TEXT NOT NULL,
            extension TEXT,
            normalized_file_type TEXT NOT NULL,
            size_bytes INTEGER,
            fs_created_at TEXT,
            fs_modified_at TEXT,
            duplicate_key TEXT,
            FOREIGN KEY (directory_id) REFERENCES directories(id)
        );
    SQL);

    $insertVolume = $pdo->prepare('INSERT INTO volumes (label, mount_path_last_seen) VALUES (?, ?)');
    $insertDir = $pdo->prepare('INSERT INTO directories (volume_id, full_path) VALUES (?, ?)');
    $insertFile = $pdo->prepare(
        'INSERT INTO files (directory_id, basename, extension, normalized_file_type, size_bytes, fs_created_at, fs_modified_at, duplicate_key)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    );

    foreach ($volumes as $volumeData) {
        $insertVolume->execute([$volumeData['volume_label'], $volumeData['mount_path_last_seen'] ?? null]);
        $volumeId = (int) $pdo->lastInsertId();

        foreach ($volumeData['directories'] as $dirData) {
            $insertDir->execute([$volumeId, $dirData['full_path']]);
            $dirId = (int) $pdo->lastInsertId();

            foreach ($dirData['files'] as $fileData) {
                $insertFile->execute([
                    $dirId,
                    $fileData['basename'],
                    $fileData['extension'] ?? null,
                    $fileData['normalized_file_type'],
                    $fileData['size_bytes'] ?? null,
                    $fileData['fs_created_at'] ?? null,
                    $fileData['fs_modified_at'] ?? null,
                    $fileData['duplicate_key'] ?? null,
                ]);
            }
        }
    }

    $connectionName = 'monique_fixture_'.bin2hex(random_bytes(4));

    config()->set("database.connections.{$connectionName}", [
        'driver' => 'sqlite',
        'database' => $databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $envPath = $root.'/mostly-unique.env';
    File::put($envPath, implode(PHP_EOL, [
        'DB_CONNECTION=sqlite',
        'DB_DATABASE='.$databasePath,
        '',
    ]));

    return [
        'connection_name' => $connectionName,
        'database_path' => $databasePath,
        'env_path' => $envPath,
    ];
}
