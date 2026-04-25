<?php

declare(strict_types=1);

namespace App\Data\Search;

use Carbon\CarbonInterface;

/**
 * @phpstan-type SearchDocumentAttributes array{
 *     source_type:string,
 *     source_table:string,
 *     source_id:string,
 *     title:string|null,
 *     body:string|null,
 *     occurred_at:CarbonInterface|null,
 *     participants:list<string>,
 *     url_or_locator:string|null,
 *     metadata:array<string, mixed>
 * }
 */
final readonly class SearchDocumentData
{
    /**
     * @param  list<string>  $participants
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceType,
        public string $sourceTable,
        public string $sourceId,
        public ?string $title,
        public ?string $body,
        public ?CarbonInterface $occurredAt,
        public array $participants = [],
        public ?string $urlOrLocator = null,
        public array $metadata = [],
    ) {}

    public function hasSearchableText(): bool
    {
        if ($this->filled($this->title)) {
            return true;
        }
        if ($this->filled($this->body)) {
            return true;
        }
        if ($this->participants !== []) {
            return true;
        }

        return $this->filled($this->urlOrLocator);
    }

    /**
     * @return SearchDocumentAttributes
     */
    public function toAttributes(): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_table' => $this->sourceTable,
            'source_id' => $this->sourceId,
            'title' => $this->blankToNull($this->title),
            'body' => $this->blankToNull($this->body),
            'occurred_at' => $this->occurredAt,
            'participants' => array_values(array_unique(array_filter(
                array_map(trim(...), $this->participants),
                static fn (string $participant): bool => $participant !== '',
            ))),
            'url_or_locator' => $this->blankToNull($this->urlOrLocator),
            'metadata' => $this->metadata,
        ];
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
