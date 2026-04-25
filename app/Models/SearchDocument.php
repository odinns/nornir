<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $source_type
 * @property string $source_table
 * @property string $source_id
 * @property string|null $title
 * @property string|null $body
 * @property CarbonImmutable|null $occurred_at
 * @property list<string>|null $participants
 * @property string|null $url_or_locator
 * @property array<string, mixed>|null $metadata
 */
class SearchDocument extends Model
{
    use Searchable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'participants' => 'array',
            'metadata' => 'array',
        ];
    }

    public function searchableAs(): string
    {
        return 'search_documents';
    }

    /**
     * @return array{
     *     id:int,
     *     source_type:string,
     *     source_table:string,
     *     source_id:string,
     *     title:string|null,
     *     body:string|null,
     *     occurred_at:string|null,
     *     participants:list<string>,
     *     url_or_locator:string|null,
     *     metadata:array<string, mixed>
     * }
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'source_table' => $this->source_table,
            'source_id' => $this->source_id,
            'title' => $this->title,
            'body' => $this->body,
            'occurred_at' => $this->occurred_at?->toJSON(),
            'participants' => $this->participants ?? [],
            'url_or_locator' => $this->url_or_locator,
            'metadata' => $this->metadata ?? [],
        ];
    }
}
