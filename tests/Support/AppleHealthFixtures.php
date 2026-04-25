<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  array{
 *     export_date?:string,
 *     me?:array<string, string>,
 *     records?:list<array{
 *         type:string,
 *         source_name:string,
 *         source_version:string,
 *         unit?:string|null,
 *         creation_date:string,
 *         start_date:string,
 *         end_date:string,
 *         value?:string|null
 *     }>,
 *     workouts?:list<array{
 *         workout_activity_type:string,
 *         source_name:string,
 *         source_version:string,
 *         creation_date:string,
 *         start_date:string,
 *         end_date:string,
 *         duration:number,
 *         duration_unit:string,
 *         total_energy_burned?:string|null,
 *         total_energy_burned_unit?:string|null,
 *         total_distance?:string|null,
 *         total_distance_unit?:string|null
 *     }>
 * } $dataset
 * @return array{root_path:string, export_xml_path:string, cda_xml_path:string}
 */
function createAppleHealthFixtureExport(string $name, array $dataset = []): array
{
    $root = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));
    $exportXmlPath = $root.'/eksport.xml';
    $cdaXmlPath = $root.'/export_cda.xml';

    File::ensureDirectoryExists($root);

    $exportDate = htmlspecialchars($dataset['export_date'] ?? '2026-04-17 15:45:06 +0200', ENT_XML1);

    $meXml = '';

    if (($dataset['me'] ?? null) !== null && is_array($dataset['me'])) {
        $attributes = collect($dataset['me'])
            ->map(fn (string $value, string $key): string => sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_XML1)))
            ->implode(' ');

        $meXml = "  <Me {$attributes}/>\n";
    }

    $recordsXml = collect($dataset['records'] ?? [])
        ->map(function (array $record): string {
            $attributes = [
                'type' => $record['type'],
                'sourceName' => $record['source_name'],
                'sourceVersion' => $record['source_version'],
                'creationDate' => $record['creation_date'],
                'startDate' => $record['start_date'],
                'endDate' => $record['end_date'],
            ];

            if (array_key_exists('unit', $record) && $record['unit'] !== null) {
                $attributes['unit'] = $record['unit'];
            }

            if (array_key_exists('value', $record) && $record['value'] !== null) {
                $attributes['value'] = $record['value'];
            }

            $compiledAttributes = collect($attributes)
                ->map(fn (string $value, string $key): string => sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_XML1)))
                ->implode(' ');

            return "  <Record {$compiledAttributes}/>";
        })
        ->implode("\n");

    $workoutsXml = collect($dataset['workouts'] ?? [])
        ->map(function (array $workout): string {
            $attributes = [
                'workoutActivityType' => $workout['workout_activity_type'],
                'sourceName' => $workout['source_name'],
                'sourceVersion' => $workout['source_version'],
                'creationDate' => $workout['creation_date'],
                'startDate' => $workout['start_date'],
                'endDate' => $workout['end_date'],
                'duration' => (string) $workout['duration'],
                'durationUnit' => $workout['duration_unit'],
            ];

            if (($workout['total_energy_burned'] ?? null) !== null) {
                $attributes['totalEnergyBurned'] = $workout['total_energy_burned'];
            }

            if (($workout['total_energy_burned_unit'] ?? null) !== null) {
                $attributes['totalEnergyBurnedUnit'] = $workout['total_energy_burned_unit'];
            }

            if (($workout['total_distance'] ?? null) !== null) {
                $attributes['totalDistance'] = $workout['total_distance'];
            }

            if (($workout['total_distance_unit'] ?? null) !== null) {
                $attributes['totalDistanceUnit'] = $workout['total_distance_unit'];
            }

            $compiledAttributes = collect($attributes)
                ->map(fn (string $value, string $key): string => sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_XML1)))
                ->implode(' ');

            return "  <Workout {$compiledAttributes}/>";
        })
        ->implode("\n");

    $body = trim($meXml.$recordsXml.($recordsXml !== '' && $workoutsXml !== '' ? "\n" : '').$workoutsXml);

    $xml = sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<HealthData locale="en_US">
  <ExportDate value="%s"/>
%s
</HealthData>
XML,
        $exportDate,
        $body,
    );

    File::put($exportXmlPath, $xml."\n");
    File::put($cdaXmlPath, <<<'XML'
<?xml version="1.0"?>
<ClinicalDocument xmlns="urn:hl7-org:v3">
  <title>Health Data Export</title>
</ClinicalDocument>
XML."\n");

    return [
        'root_path' => $root,
        'export_xml_path' => $exportXmlPath,
        'cda_xml_path' => $cdaXmlPath,
    ];
}
