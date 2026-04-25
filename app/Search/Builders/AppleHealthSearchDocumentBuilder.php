<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\AppleHealthRecord;
use App\Models\AppleHealthWorkout;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class AppleHealthSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'apple-health';
    }

    public function build(): iterable
    {
        foreach (AppleHealthRecord::query()->lazyById() as $record) {
            yield new SearchDocumentData(
                sourceType: 'apple-health',
                sourceTable: 'apple_health_records',
                sourceId: $record->canonical_key,
                title: $record->record_type,
                body: $this->joinText([$record->source_name, $record->value, $record->unit]),
                occurredAt: $record->start_at ?? $record->creation_at,
                metadata: ['source_version' => $record->source_version],
            );
        }

        foreach (AppleHealthWorkout::query()->lazyById() as $workout) {
            yield new SearchDocumentData(
                sourceType: 'apple-health',
                sourceTable: 'apple_health_workouts',
                sourceId: $workout->canonical_key,
                title: $workout->workout_activity_type,
                body: $this->joinText([
                    $workout->source_name,
                    $workout->duration === null ? null : $workout->duration.' '.$workout->duration_unit,
                    $workout->total_energy_burned === null ? null : $workout->total_energy_burned.' '.$workout->total_energy_burned_unit,
                    $workout->total_distance === null ? null : $workout->total_distance.' '.$workout->total_distance_unit,
                ]),
                occurredAt: $workout->start_at ?? $workout->creation_at,
                metadata: ['source_version' => $workout->source_version],
            );
        }
    }
}
