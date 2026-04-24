<?php

declare(strict_types=1);

use App\Services\Wayback\WaybackCaptureClassifier;

it('classifies biography-relevant wayback pages by surface', function (string $url, string $expectedSurface): void {
    $result = (new WaybackCaptureClassifier)->classify(
        $url,
        '<html><body><p>Odinn wrote about projects and personal history.</p></body></html>',
        'Odinn wrote about projects and personal history.',
    );

    expect($result['verdict'])->toBe('accepted');
    expect($result['biographical_surface'])->toBe($expectedSurface);
})->with([
    ['http://odinns.dk/', 'homepage'],
    ['http://odinns.dk/about', 'about'],
    ['http://odinns.dk/projects/goldware', 'project'],
    ['http://odinns.dk/downloads', 'download'],
    ['http://odinns.dk/contact', 'contact'],
    ['http://odinns.dk/archive/2003', 'blog'],
    ['http://odinns.dk/blog/post', 'blog'],
    ['http://odinns.dk/misc', 'unknown'],
]);

it('rejects non-biographical wayback noise', function (string $url, string $html, string $reason): void {
    $result = (new WaybackCaptureClassifier)->classify($url, $html, strip_tags($html));

    expect($result['verdict'])->toBe('rejected');
    expect($result['reject_reason'])->toBe($reason);
})->with([
    ['http://odinns.dk/style.css', '<html></html>', 'static-asset'],
    ['http://odinns.dk/', '<html><body>This domain is parked</body></html>', 'parking'],
    ['http://odinns.dk/', '<html><body>Registrar landing page</body></html>', 'registrar'],
    ['http://odinns.dk/', '<html><body><script>window.location = "/new"</script></body></html>', 'redirect'],
    ['http://odinns.dk/', '<html><body>Cheap casino links</body></html>', 'spam'],
    ['http://odinns.dk/', '<html><body>Please sign in to continue</body></html>', 'login'],
]);
