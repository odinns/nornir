<?php

declare(strict_types=1);

namespace App\Services\Gmail;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

class GmailTriageDateRangeParser
{
    public function parse(?string $since, ?string $on, ?string $window): GmailTriageDateRange
    {
        $since = $this->normalize($since);
        $on = $this->normalize($on);
        $window = $this->normalize($window);
        $provided = array_filter([$since, $on, $window]);

        if ($provided === []) {
            return $this->parseWindow('last 7 days');
        }

        if (count($provided) > 1) {
            throw new InvalidArgumentException('Use only one of --since, --on, or --window.');
        }

        if ($window !== null) {
            return $this->parseWindow($window);
        }

        if ($on !== null) {
            return $this->parseDay($on);
        }

        if ($since !== null) {
            return $this->parseSince($since);
        }

        throw new InvalidArgumentException('Use only one of --since, --on, or --window.');
    }

    private function parseWindow(string $input): GmailTriageDateRange
    {
        $now = $this->now();

        if (preg_match('/^last\s+(\d+)\s+days$/i', $input, $matches) === 1) {
            $days = (int) $matches[1];

            if ($days < 1) {
                throw $this->invalidDateInput($input);
            }

            return new GmailTriageDateRange(
                start: $now->startOfDay()->subDays($days),
                end: $now,
            );
        }

        throw $this->invalidDateInput($input);
    }

    private function parseDay(string $input): GmailTriageDateRange
    {
        $now = $this->now();

        if (preg_match('/^(\d+)\s+days\s+ago$/i', $input, $matches) === 1) {
            $days = (int) $matches[1];

            if ($days < 0) {
                throw $this->invalidDateInput($input);
            }

            return $this->fullDayRange($now->startOfDay()->subDays($days));
        }

        return $this->fullDayRange($this->parseAbsolute($input)->startOfDay());
    }

    private function parseSince(string $input): GmailTriageDateRange
    {
        if (preg_match('/^\d+\s+days\s+ago$/i', $input) === 1) {
            return $this->parseDay($input);
        }

        if (preg_match('/^last\s+\d+\s+days$/i', $input) === 1) {
            return $this->parseWindow($input);
        }

        $parsed = $this->parseAbsolute($input);

        if ($this->looksLikeDateOnly($input)) {
            $parsed = $parsed->startOfDay();
        }

        return new GmailTriageDateRange(
            start: $parsed,
            end: null,
        );
    }

    private function parseAbsolute(string $input): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($input, $this->timezone());
        } catch (Throwable) {
            throw $this->invalidDateInput($input);
        }
    }

    private function fullDayRange(CarbonImmutable $date): GmailTriageDateRange
    {
        return new GmailTriageDateRange(
            start: $date->startOfDay(),
            end: $date->setTime(23, 59, 59),
        );
    }

    private function looksLikeDateOnly(string $input): bool
    {
        $trimmed = trim($input);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1
            || preg_match('/^\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}$/', $trimmed) === 1
            || preg_match('/^[A-Za-z]{3,9}\s+\d{1,2}\s+\d{4}$/', $trimmed) === 1;
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    private function timezone(): string
    {
        $timezone = date_default_timezone_get();

        return $timezone !== ''
            ? $timezone
            : 'UTC';
    }

    private function normalize(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function invalidDateInput(string $input): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "Could not parse date input [{$input}]. Accepted date inputs include YYYY-MM-DD, ".
            'YYYY-MM-DD HH:MM, April 20 2026, last 7 days, and 2 days ago.'
        );
    }
}
