<?php

declare(strict_types=1);

namespace App\Services\Wayback;

final readonly class WaybackCaptureClassifier
{
    /**
     * @return array{verdict:string, reject_reason:?string, biographical_surface:?string, evidence_summary:?string}
     */
    public function classify(string $originalUrl, string $html, string $authoredText): array
    {
        $url = strtolower($originalUrl);
        $text = strtolower(strip_tags($html.' '.$authoredText));

        if (preg_match('/\.(?:css|js|png|jpe?g|gif|webp|svg|ico|zip|gz|pdf|mp3|mp4)(?:[?#].*)?$/', $url) === 1) {
            return $this->reject('static-asset');
        }

        foreach ([
            'domain is parked' => 'parking',
            'buy this domain' => 'parking',
            'registrar' => 'registrar',
            'this domain name has been registered' => 'registrar',
            'window.location' => 'redirect',
            'meta http-equiv="refresh"' => 'redirect',
            'casino' => 'spam',
            'viagra' => 'spam',
            'login' => 'login',
            'sign in' => 'login',
        ] as $needle => $reason) {
            if (str_contains($text, $needle)) {
                return $this->reject($reason);
            }
        }

        $surface = $this->surface($url);

        return [
            'verdict' => 'accepted',
            'reject_reason' => null,
            'biographical_surface' => $surface,
            'evidence_summary' => 'Archived '.$surface.' page with authored text suitable for biography review.',
        ];
    }

    /**
     * @return array{verdict:string, reject_reason:string, biographical_surface:null, evidence_summary:null}
     */
    private function reject(string $reason): array
    {
        return [
            'verdict' => 'rejected',
            'reject_reason' => $reason,
            'biographical_surface' => null,
            'evidence_summary' => null,
        ];
    }

    private function surface(string $url): string
    {
        if (preg_match('~/(about|om|bio|cv|resume)(?:[/._-]|$)~', $url) === 1) {
            return 'about';
        }

        if (preg_match('~/(project|projects|portfolio|work|goldware)(?:[/._-]|$)~', $url) === 1) {
            return 'project';
        }

        if (preg_match('~/(blog|journal|news|archive)(?:[/._-]|$)~', $url) === 1) {
            return 'blog';
        }

        if (preg_match('~/(contact|kontakt)(?:[/._-]|$)~', $url) === 1) {
            return 'contact';
        }

        if (preg_match('~/(download|downloads|software)(?:[/._-]|$)~', $url) === 1) {
            return 'download';
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

        if ($path === '' || $path === '/') {
            return 'homepage';
        }

        return 'unknown';
    }
}
