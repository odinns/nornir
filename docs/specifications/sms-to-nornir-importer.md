# SMS To Nornir Importer

## Goal

Import SMS history into canonical MySQL tables for chronology-heavy biography work.

## Canonical source

Local structured SMS export or source database.

## Inputs

- source path
- optional source-set label
- dry-run or validate-only flags

## Output structure

- canonical `sms_*` tables
- run artifacts under `data/imports/sms/`

## MySQL storage model

- `sms_source_sets`
- `sms_messages`
- `sms_participants`
- `sms_conversations`
- `sms_attachments`

## Data model

Preserve message ids, timestamps, direction, participant identifiers, and attachment references.

## Import rules

- message identity comes first
- conversation grouping may be derived but reproducible

## Incremental behavior

- rerun by source-set and stable message identity

## Validation

- timestamp integrity
- participant mapping sanity
- attachment reference handling

## Wiki compilation handoff

- source pages and Muninn evidence derive from canonical message rows

## Forbidden behavior

- no reinterpretation of missing contact data as truth

## Review checklist

- chronology is preserved
- message identity stays stable

## Acceptance checks

- SMS can anchor timelines without fuzzy post-processing
