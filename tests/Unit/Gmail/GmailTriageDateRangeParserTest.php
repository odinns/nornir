<?php

declare(strict_types=1);

use App\Services\Gmail\GmailTriageDateRangeParser;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    date_default_timezone_set('Europe/Copenhagen');
});

it('parses last seven days from local midnight through now', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-04-20 15:45:00', 'Europe/Copenhagen'));

    $range = app(GmailTriageDateRangeParser::class)->parse(
        since: null,
        on: null,
        window: 'last 7 days',
    );

    if ($range->start === null || $range->end === null) {
        throw new RuntimeException('Expected last-seven-days parsing to return a bounded range.');
    }

    expect($range->start->toIso8601String())->toBe('2026-04-13T00:00:00+02:00');
    expect($range->end->toIso8601String())->toBe('2026-04-20T15:45:00+02:00');
});

it('parses two days ago as the full local calendar day', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-04-20 15:45:00', 'Europe/Copenhagen'));

    $range = app(GmailTriageDateRangeParser::class)->parse(
        since: null,
        on: '2 days ago',
        window: null,
    );

    if ($range->start === null || $range->end === null) {
        throw new RuntimeException('Expected day parsing to return a bounded range.');
    }

    expect($range->start->toIso8601String())->toBe('2026-04-18T00:00:00+02:00');
    expect($range->end->toIso8601String())->toBe('2026-04-18T23:59:59+02:00');
});

it('parses absolute date-only input as the full local calendar day', function (): void {
    $range = app(GmailTriageDateRangeParser::class)->parse(
        since: null,
        on: '2026-04-18',
        window: null,
    );

    if ($range->start === null || $range->end === null) {
        throw new RuntimeException('Expected date-only parsing to return a bounded range.');
    }

    expect($range->start->toIso8601String())->toBe('2026-04-18T00:00:00+02:00');
    expect($range->end->toIso8601String())->toBe('2026-04-18T23:59:59+02:00');
});

it('parses absolute datetime input without widening it to the full day', function (): void {
    $range = app(GmailTriageDateRangeParser::class)->parse(
        since: '2026-04-18 14:30',
        on: null,
        window: null,
    );

    if ($range->start === null) {
        throw new RuntimeException('Expected since parsing to return a start date.');
    }

    expect($range->start->toIso8601String())->toBe('2026-04-18T14:30:00+02:00');
    expect($range->end)->toBeNull();
});

it('supports natural language absolute inputs', function (): void {
    $range = app(GmailTriageDateRangeParser::class)->parse(
        since: null,
        on: 'April 18 2026',
        window: null,
    );

    if ($range->start === null || $range->end === null) {
        throw new RuntimeException('Expected natural language date parsing to return a bounded range.');
    }

    expect($range->start->toIso8601String())->toBe('2026-04-18T00:00:00+02:00');
    expect($range->end->toIso8601String())->toBe('2026-04-18T23:59:59+02:00');
});

it('rejects invalid date input with a useful message', function (): void {
    expect(fn () => app(GmailTriageDateRangeParser::class)->parse(
        since: null,
        on: null,
        window: 'the recent past or whatever',
    ))->toThrow(InvalidArgumentException::class, 'Accepted date inputs include');
});
