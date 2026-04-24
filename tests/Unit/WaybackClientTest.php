<?php

declare(strict_types=1);

use App\Services\Wayback\WaybackClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('encodes cdx filters as repeated query parameters', function (): void {
    $requestedUrl = null;

    Http::fake(function (Request $request) use (&$requestedUrl) {
        $requestedUrl = $request->url();

        return Http::response([
            ['urlkey', 'timestamp', 'original', 'mimetype', 'statuscode', 'digest', 'length'],
        ]);
    });

    app(WaybackClient::class)->cdxCaptures(
        scope: 'tantraviking.dk',
        matchMode: 'host',
        from: null,
        to: null,
        limit: 10,
        delayMs: 0,
    );

    expect($requestedUrl)->toContain('filter=statuscode%3A200')
        ->and($requestedUrl)->toContain('filter=mimetype%3Atext%2Fhtml')
        ->and($requestedUrl)->not->toContain('filter%5B0%5D');
});

it('retries transient wayback connection failures', function (): void {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new ConnectionException('cURL error 7: Failed to connect to web.archive.org port 443');
        }

        return Http::response('<html>archived</html>');
    });

    $html = app(WaybackClient::class)->replayHtml(
        timestamp: '20140111081612',
        originalUrl: 'http://tantraviking.dk/tantra/',
        delayMs: 0,
    );

    expect($html)->toBe('<html>archived</html>')
        ->and($attempts)->toBe(2);
});

it('retries temporary wayback response statuses', function (): void {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            return Http::response('slow down', 503);
        }

        return Http::response('<html>archived</html>');
    });

    $html = app(WaybackClient::class)->replayHtml(
        timestamp: '20140111081612',
        originalUrl: 'http://tantraviking.dk/tantra/',
        delayMs: 0,
    );

    expect($html)->toBe('<html>archived</html>')
        ->and($attempts)->toBe(2);
});
