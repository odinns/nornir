<?php

declare(strict_types=1);

namespace App\Services\Wayback;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class WaybackScreenshotter
{
    /**
     * @return array{path:string, hash:string}
     */
    public function capture(string $url, string $path): array
    {
        File::ensureDirectoryExists(dirname($path));

        $process = new Process([
            'node',
            base_path('resources/js/wayback-screenshot.js'),
            $url,
            $path,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Wayback screenshot failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return [
            'path' => $path,
            'hash' => hash_file('sha256', $path) ?: '',
        ];
    }
}
