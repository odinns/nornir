<?php

declare(strict_types=1);

use App\Services\Wayback\WaybackTextExtractor;

it('removes wayback chrome and executable noise while preserving authored text and metadata', function (): void {
    $result = (new WaybackTextExtractor)->extract(<<<'HTML'
        <html>
            <head>
                <title>Old Odinn Site</title>
                <meta name="description" content="Archived personal project page">
                <style>.hidden { display: none; }</style>
                <script>console.log('noise')</script>
            </head>
            <body>
                <div id="wm-ipp">Wayback chrome controls</div>
                <h1>Goldware</h1>
                <p>I built tiny web tools before the mailbox evidence starts.</p>
                <noscript>noscript noise</noscript>
            </body>
        </html>
        HTML);

    expect($result['title'])->toBe('Old Odinn Site');
    expect($result['meta_description'])->toBe('Archived personal project page');
    expect($result['authored_text'])->toContain('Goldware');
    expect($result['authored_text'])->toContain('before the mailbox evidence starts');
    expect($result['authored_text'])->not->toContain('Wayback chrome controls');
    expect($result['authored_text'])->not->toContain('console.log');
    expect($result['authored_text'])->not->toContain('noscript noise');
});
