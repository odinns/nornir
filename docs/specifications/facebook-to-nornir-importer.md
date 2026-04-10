# Facebook To Nornir Importer

## Goal

Import Facebook archive threads and messages into canonical MySQL tables for timeline and relationship use.

## Canonical source

Facebook export archive JSON.

## Inputs

- archive path
- optional thread scope filters
- dry-run or validate-only flags

## Output structure

- canonical `facebook_*` tables
- artifacts under `data/imports/facebook/`

## MySQL storage model

- `facebook_archives`
- `facebook_threads`
- `facebook_participants`
- `facebook_messages`
- `facebook_reactions`
- `facebook_attachments`

## Data model

Preserve archive thread identity, message timestamps, participants, and attachment references.

## Import rules

- keep thread boundaries explicit
- preserve sender identity and timestamps
- import attachment metadata without ingesting binaries

## Incremental behavior

- rerun by archive and message identity

## Validation

- thread and message counts
- participant joins
- attachment reference integrity

## Wiki compilation handoff

- source pages and evidence bundles derive from MySQL rows

## Forbidden behavior

- no binary copying into canonical storage
- no relationship inference during import

## Review checklist

- thread identity survives
- attachments remain references

## Acceptance checks

- Messenger history can support Muninn without archive rescans
