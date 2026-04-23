<?php

declare(strict_types=1);

namespace App\Services\Gmail;

use Carbon\CarbonImmutable;

final readonly class GmailTriageDateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public ?CarbonImmutable $end,
    ) {}
}
