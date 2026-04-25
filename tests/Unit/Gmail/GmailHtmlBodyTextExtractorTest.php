<?php

declare(strict_types=1);

use App\Services\Gmail\GmailHtmlBodyTextExtractor;

it('converts basic html to readable text', function (): void {
    $text = app(GmailHtmlBodyTextExtractor::class)->extract(
        '<p>Hello <strong>Odinn</strong></p><p><a href="https://example.com">Read more</a></p>',
    );

    expect($text)
        ->toContain('Hello Odinn')
        ->toContain('Read more');
});

it('handles malformed html', function (): void {
    $text = app(GmailHtmlBodyTextExtractor::class)->extract('<div><p>Broken <strong>but readable');

    expect($text)->toContain('Broken but readable');
});

it('returns null for empty or noisy html', function (string $html): void {
    expect(app(GmailHtmlBodyTextExtractor::class)->extract($html))->toBeNull();
})->with([
    'empty' => '',
    'whitespace' => " \n\t ",
    'tags only' => '<div><br></div>',
]);
