<?php

declare(strict_types=1);

arch('the application follows the Laravel preset')
    ->preset()
    ->laravel();

test('debugging helpers are not used in application code', function (): void {
    expect([
        'dd',
        'dump',
        'ray',
    ])->not->toBeUsed();
});

test('controllers stay inside the http layer', function (): void {
    expect('App\Http\Controllers')
        ->toOnlyBeUsedIn([
            'App\Http',
            'App\Providers',
            'routes',
        ]);
});
