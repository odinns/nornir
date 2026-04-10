<?php

declare(strict_types=1);

it('returns a successful response for the root route', function (): void {
    $this->get('/')->assertOk();
});
