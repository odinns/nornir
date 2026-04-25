<?php

declare(strict_types=1);

namespace App\Services\Wayback;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WaybackClient
{
    private const array BACKOFF_MILLISECONDS = [1000, 3000, 10000, 30000];

    private const array RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

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
        $query = array_filter([
            'url' => $this->scopeForCdx($scope, $matchMode),
            'output' => 'json',
            'fl' => 'urlkey,timestamp,original,mimetype,statuscode,digest,length',
            'filter' => ['statuscode:200', 'mimetype:text/html'],
            'collapse' => 'digest',
            'from' => $from,
            'to' => $to,
            'matchType' => $matchMode,
        ], static fn (mixed $value): bool => $value !== null);

        if ($limit > 0) {
            $query['limit'] = $limit;
        }

        $response = $this->getWithRetry('https://web.archive.org/cdx', $query, $delayMs);

        return $this->capturesFromJson($response->json());
    }

    public function cdxCaptureCount(
        string $scope,
        string $matchMode,
        ?string $from,
        ?string $to,
        int $delayMs,
    ): int {
        $response = $this->getWithRetry('https://web.archive.org/cdx', array_filter([
            'url' => $this->scopeForCdx($scope, $matchMode),
            'output' => 'json',
            'fl' => 'timestamp',
            'filter' => ['statuscode:200', 'mimetype:text/html'],
            'collapse' => 'digest',
            'from' => $from,
            'to' => $to,
            'matchType' => $matchMode,
        ], static fn (mixed $value): bool => $value !== null), $delayMs);

        $json = $response->json();

        if (! is_array($json) || $json === []) {
            return 0;
        }

        return max(0, count($json) - 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cdxSnapshots(
        string $scope,
        string $matchMode,
        ?string $from,
        ?string $to,
        bool $importableOnly,
        int $delayMs,
    ): array {
        $query = array_filter([
            'url' => $this->scopeForCdx($scope, $matchMode),
            'output' => 'json',
            'fl' => 'timestamp,original,statuscode,mimetype,digest,length',
            'from' => $from,
            'to' => $to,
            'matchType' => $matchMode,
        ], static fn (mixed $value): bool => $value !== null);

        if ($importableOnly) {
            $query['filter'] = ['statuscode:200', 'mimetype:text/html'];
        }

        $response = $this->getWithRetry('https://web.archive.org/cdx', $query, $delayMs);

        return $this->capturesFromJson($response->json());
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
        $fullUrl = $this->urlWithQuery($url, $query);
        $attempt = 0;

        while (true) {
            try {
                $response = Http::timeout(60)->get($fullUrl);
            } catch (ConnectionException $connectionException) {
                if (! $this->canRetry($attempt)) {
                    throw $connectionException;
                }

                $this->sleepBeforeRetry($attempt);
                $attempt++;

                continue;
            }

            if (! in_array($response->status(), self::RETRYABLE_STATUSES, true) || ! $this->canRetry($attempt)) {
                break;
            }

            $this->sleepBeforeRetry($attempt);
            $attempt++;
        }

        if (! $response->successful()) {
            $response->throw();
        }

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function urlWithQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        return $url.'?'.$this->queryString($query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function queryString(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = $this->queryPart($key, $item);
                }

                continue;
            }

            $parts[] = $this->queryPart($key, $value);
        }

        return implode('&', $parts);
    }

    private function queryPart(string $key, mixed $value): string
    {
        return rawurlencode($key).'='.rawurlencode((string) $value);
    }

    private function canRetry(int $attempt): bool
    {
        return $attempt < count(self::BACKOFF_MILLISECONDS);
    }

    private function sleepBeforeRetry(int $attempt): void
    {
        usleep((self::BACKOFF_MILLISECONDS[$attempt] ?? 30000) * 1000);
    }

    private function scopeForCdx(string $scope, string $matchMode): string
    {
        if ($matchMode === 'host') {
            return (string) (parse_url(str_contains($scope, '://') ? $scope : 'https://'.$scope, PHP_URL_HOST) ?: $scope);
        }

        return $scope;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function capturesFromJson(mixed $json): array
    {
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
}
