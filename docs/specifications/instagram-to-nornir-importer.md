# Instagram To Nornir Importer

## Goal

Provide a future-safe importer for whatever bounded Instagram data is legitimately available.

## Canonical source

Supported archive export or official API response.

## Inputs

- archive path or API scope config
- dry-run or validate-only flags

## Output structure

- canonical `instagram_*` tables
- run artifacts under `data/imports/instagram/`

## MySQL storage model

- `instagram_accounts`
- `instagram_posts`
- `instagram_media_refs`
- `instagram_profile_snapshots`

## Data model

Store only entities actually available from the supported source mode.

## Import rules

- archive-first when possible
- API second when stable and permitted
- handle sparse or tiny datasets gracefully

## Incremental behavior

- rerun by account and stable post identity where available

## Validation

- source capability detection
- schema-shape validation for the chosen source mode

## Wiki compilation handoff

- produce source pages and timeline-supporting evidence only when data exists

## Forbidden behavior

- no scraping-first implementation
- no fake completeness when the source data is thin

## Review checklist

- bounded access mode is explicit
- sparse data is treated honestly

## Acceptance checks

- Instagram can be added later without redesigning the importer framework
