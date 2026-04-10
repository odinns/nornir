# Twitter To Nornir Importer

## Goal

Import Twitter archive data into canonical MySQL tables for public timeline and expression history.

## Canonical source

Twitter/X export archive datasets.

## Inputs

- archive path
- optional dataset scope
- dry-run or validate-only flags

## Output structure

- canonical `twitter_*` tables
- run artifacts under `data/imports/twitter/`

## MySQL storage model

- `twitter_archives`
- `twitter_tweets`
- `twitter_media_refs`
- `twitter_profile_snapshots`
- `twitter_interactions`

## Data model

Preserve tweet ids, timestamps, text, conversation or reply linkage, and media references.

## Import rules

- import from declared archive datasets
- keep media as referenced metadata

## Incremental behavior

- rerun by archive and stable tweet identity

## Validation

- dataset presence
- tweet counts
- reply linkage where available

## Wiki compilation handoff

- source pages compile from canonical tweet rows

## Forbidden behavior

- no scraping-first implementation
- no collapsing profile snapshots into one fake timeless profile

## Review checklist

- archive dataset truth is respected
- public-expression chronology survives

## Acceptance checks

- timeline-supporting social evidence is queryable from MySQL
