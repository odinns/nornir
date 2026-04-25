<?php

declare(strict_types=1);

namespace App\Search\Builders\Concerns;

trait BuildsSearchDocuments
{
    /**
     * @param  array<mixed>  $parts
     */
    private function joinText(array $parts): ?string
    {
        $text = implode("\n", array_values(array_filter(
            array_map(static fn (mixed $part): string => trim((string) $part), $parts),
            static fn (string $part): bool => $part !== '',
        )));

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<mixed>  $participants
     * @return list<string>
     */
    private function participants(array $participants): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $participant): string => trim((string) $participant), $participants),
            static fn (string $participant): bool => $participant !== '',
        )));
    }
}
