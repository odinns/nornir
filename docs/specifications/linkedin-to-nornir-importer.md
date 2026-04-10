# LinkedIn To Nornir Importer

## Goal

Provide a future-safe importer for supported LinkedIn export or API data, mainly for public milestone evidence.

## Canonical source

Supported archive export or official API response.

## Inputs

- archive path or API scope config
- dry-run or validate-only flags

## Output structure

- canonical `linkedin_*` tables
- run artifacts under `data/imports/linkedin/`

## MySQL storage model

- `linkedin_accounts`
- `linkedin_profile_snapshots`
- `linkedin_positions`
- `linkedin_organizations`
- `linkedin_posts`

## Data model

Preserve profile snapshots, dated positions, organizations, and posts only where the source truth supports them.

## Import rules

- archive-first when possible
- API second when stable and permitted
- prioritize milestone-bearing data over ornamental completeness

## Incremental behavior

- rerun by account and stable entity identity where available

## Validation

- source capability detection
- date-range preservation
- organization-position joins

## Wiki compilation handoff

- source pages and timeline evidence derive from canonical rows

## Forbidden behavior

- no scraping-first implementation
- no inflated data model for data that does not exist

## Review checklist

- public milestone utility is clear
- sparse data remains useful instead of awkward

## Acceptance checks

- LinkedIn can be imported later without warping the architecture
