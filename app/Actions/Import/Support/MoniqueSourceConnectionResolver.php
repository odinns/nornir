<?php

declare(strict_types=1);

namespace App\Actions\Import\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class MoniqueSourceConnectionResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(string $envPath): array
    {
        if (! File::exists($envPath) || ! File::isFile($envPath)) {
            throw new InvalidArgumentException('Media collection source env file was not found.');
        }

        $values = $this->parseEnv((string) File::get($envPath));
        $driver = $values['DB_CONNECTION'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw new InvalidArgumentException('Media collection source env file is missing DB_CONNECTION.');
        }

        if ($driver === 'sqlite') {
            $database = $values['DB_DATABASE'] ?? null;

            if (! is_string($database) || $database === '') {
                throw new InvalidArgumentException('Media collection source env file is missing DB_DATABASE.');
            }

            return [
                'driver' => 'sqlite',
                'database' => $database,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        if ($driver === 'mysql') {
            $database = $values['DB_DATABASE'] ?? null;

            if (! is_string($database) || $database === '') {
                throw new InvalidArgumentException('Media collection source env file is missing DB_DATABASE.');
            }

            return [
                'driver' => 'mysql',
                'host' => $values['DB_HOST'] ?? '127.0.0.1',
                'port' => $values['DB_PORT'] ?? '3306',
                'database' => $database,
                'username' => $values['DB_USERNAME'] ?? 'root',
                'password' => $values['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'timezone' => '+00:00',
                'strict' => true,
            ];
        }

        throw new InvalidArgumentException("Unsupported media collection source driver [{$driver}].");
    }

    public function connect(string $envPath): ConnectionInterface
    {
        $connectionName = 'monique_source_'.sha1($envPath);

        config([
            "database.connections.{$connectionName}" => $this->resolveConfig($envPath),
        ]);

        DB::purge($connectionName);

        return DB::connection($connectionName);
    }

    /**
     * @return array<string, string>
     */
    private function parseEnv(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            $separator = strpos($trimmed, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $separator));
            $value = trim(substr($trimmed, $separator + 1));

            if ($key === '') {
                continue;
            }

            $values[$key] = trim($value, "\"'");
        }

        return $values;
    }
}
