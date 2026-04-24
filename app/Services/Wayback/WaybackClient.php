<?php

declare(strict_types=1);

namespace App\Services\Wayback;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WaybackClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function cdxCaptures(
        string $scope,
        string $matchMode,
        ?string $from,
        ?string $to,
        int $limit,
        int $delayMs,
    ): array {
        $response = $this->getWithRetry('https://web.archive.org/cdx', array_filter([
            'url' => $this->scopeForCdx($scope, $matchMode),
            'output' => 'json',
            'fl' => 'urlkey,timestamp,original,mimetype,statuscode,digest,length',
            'filter' => ['statuscode:200', 'mimetype:text/html'],
            'collapse' => 'digest',
            'limit' => $limit,
            'from' => $from,
            'to' => $to,
            'matchType' => $matchMode,
        ], static fn (mixed $value): bool => $value !== null), $delayMs);

        $json = $response->json();

        if (! is_array($json) || $json === []) {
            return [];
        }

        $header = array_shift($json);

        if (! is_array($header)) {
            return [];
        }

        $captures = [];

        foreach ($json as $row) {
            if (! is_array($row)) {
                continue;
            }

            $capture = [];

            foreach (array_values($header) as $index => $name) {
                if (! is_string($name)) {
                    continue;
                }

                $capture[$name] = $row[$index] ?? null;
            }

            $captures[] = $capture;
        }

        return $captures;
    }

    public function replayHtml(string $timestamp, string $originalUrl, int $delayMs): string
    {
        $response = $this->getWithRetry($this->replayUrl($timestamp, $originalUrl), [], $delayMs);

        return $response->body();
    }

    public function replayUrl(string $timestamp, string $originalUrl): string
    {
        return 'https://web.archive.org/web/'.$timestamp.'id_/'.$originalUrl;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function getWithRetry(string $url, array $query, int $delayMs): Response
    {
        $response = Http::retry(3, 500, static function (mixed $exception, mixed $request): bool {
            unset($request);

            return str_contains(strtolower((string) $exception), 'timed out')
                || str_contains(strtolower((string) $exception), 'connection');
        })->get($url, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            $response = Http::retry(3, 1000)->get($url, $query);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Wayback request failed with HTTP {$response->status()} for {$url}.");
        }

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return $response;
    }

    private function scopeForCdx(string $scope, string $matchMode): string
    {
        if ($matchMode === 'host') {
            return (string) (parse_url(str_contains($scope, '://') ? $scope : 'https://'.$scope, PHP_URL_HOST) ?: $scope);
        }

        return $scope;
    }
}
