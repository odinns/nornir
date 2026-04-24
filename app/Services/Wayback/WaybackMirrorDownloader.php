<?php

declare(strict_types=1);

namespace App\Services\Wayback;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class WaybackMirrorDownloader
{
    public function mirror(string $url, string $directory): string
    {
        $wget = (new ExecutableFinder)->find('wget');

        if ($wget === null) {
            throw new RuntimeException('Wayback mirror requested, but wget is not available.');
        }

        File::ensureDirectoryExists($directory);

        $process = new Process([
            $wget,
            '--page-requisites',
            '--convert-links',
            '--adjust-extension',
            '--no-parent',
            '--directory-prefix',
            $directory,
            $url,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Wayback mirror failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return $directory;
    }
}
