<?php

declare(strict_types=1);

use Tests\TestCase;

require_once __DIR__.'/Support/SmsFixtures.php';
require_once __DIR__.'/Support/FacebookFixtures.php';
require_once __DIR__.'/Support/TwitterFixtures.php';
require_once __DIR__.'/Support/LinkedInFixtures.php';
require_once __DIR__.'/Support/FidonetFixtures.php';
require_once __DIR__.'/Support/GmailFixtures.php';
require_once __DIR__.'/Support/InstagramFixtures.php';
require_once __DIR__.'/Support/MediaCollectionFixtures.php';
require_once __DIR__.'/Support/AppleHealthFixtures.php';

uses(TestCase::class)->in('Feature');
