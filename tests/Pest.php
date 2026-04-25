<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

require_once __DIR__.'/Support/AppleMessagesFixtures.php';
require_once __DIR__.'/Support/FacebookFixtures.php';
require_once __DIR__.'/Support/TwitterFixtures.php';
require_once __DIR__.'/Support/LinkedInFixtures.php';
require_once __DIR__.'/Support/FidonetFixtures.php';
require_once __DIR__.'/Support/GmailFixtures.php';
require_once __DIR__.'/Support/InstagramFixtures.php';
require_once __DIR__.'/Support/MediaCollectionFixtures.php';
require_once __DIR__.'/Support/AppleHealthFixtures.php';

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * @param  array<string, mixed>  $parameters
 */
function artisanCommand(TestCase $testCase, string $command, array $parameters = []): PendingCommand
{
    $pendingCommand = $testCase->artisan($command, $parameters);

    if (! $pendingCommand instanceof PendingCommand) {
        throw new RuntimeException('Console output mocking must be enabled for fluent command assertions.');
    }

    return $pendingCommand;
}
