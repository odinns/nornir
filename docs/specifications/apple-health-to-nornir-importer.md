# Apple Health To Nornir Importer

## Goal

Import Apple Health export data into canonical MySQL tables for bounded medical-track evidence work.

## Canonical source

Apple Health `eksport.xml` from an export directory or direct file path.

## Inputs

- source path
- optional direct XML file path instead of directory

## Output structure

- canonical `apple_health_*` tables
- reviewable run mirrors under `data/runs/`
- run artifacts under `data/imports/apple-health/`

## MySQL storage model

- `apple_health_source_sets`
- `apple_health_records`
- `apple_health_workouts`
- `apple_health_record_observations`
- `apple_health_workout_observations`

## Data model

Preserve Apple Health record types, workout activity types, source app metadata, normalized timestamps, unit/value fields, and raw entry payloads.

## Import rules

- import all `Record` rows generically
- import all `Workout` rows generically
- skip `Me` profile characteristics and `ExportDate` in v1
- use source-prefixed canonical tables rather than a fake universal medical schema

## Incremental behavior

- rerun by stable canonical entry identity and source-set observation
- later incomplete exports must not delete earlier canonical rows

## Validation

- malformed or missing `eksport.xml` fails clearly
- timestamp normalization is explicit
- handoff scope derives from canonical rows only

## Source handoff

- source pages compile from canonical Apple Health rows
- handoff scope derives from source sets and canonical row counts

## Forbidden behavior

- no CDA-first import path in v1
- no profile canonicalization in v1
- no destructive dedupe of older health rows

## Review checklist

- `eksport.xml` stays the canonical importer input
- source metadata remains traceable
- profile data stays out of canonical storage

## Acceptance checks

- Apple Health can be imported repeatably without losing provenance
