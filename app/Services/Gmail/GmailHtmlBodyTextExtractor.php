<?php

declare(strict_types=1);

namespace App\Services\Gmail;

use Soundasleep\Html2Text;

class GmailHtmlBodyTextExtractor
{
    public function extract(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = Html2Text::convert($html, [
            'ignore_errors' => true,
            'drop_links' => false,
        ]);

        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
