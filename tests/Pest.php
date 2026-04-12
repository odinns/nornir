<?php

declare(strict_types=1);

use Tests\TestCase;

require_once __DIR__.'/Support/SmsFixtures.php';
require_once __DIR__.'/Support/FacebookFixtures.php';
require_once __DIR__.'/Support/TwitterFixtures.php';
require_once __DIR__.'/Support/LinkedInFixtures.php';
require_once __DIR__.'/Support/FidonetFixtures.php';

uses(TestCase::class)->in('Feature');
