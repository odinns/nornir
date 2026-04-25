# Apple Messages To Nornir Importer

## Goal

Import Apple Messages history from `chat.db` into canonical MySQL tables for chronology-heavy biography work.

## Canonical source

Local Apple Messages `chat.db` database or equivalent structured export.

## Inputs

- source path to `chat.db` or a directory containing `chat.db`
- optional `--attachments-root=`
- optional repeated `--contacts-db=`

## Output structure

- canonical `apple_messages_*` tables
- run artifacts under `data/imports/apple-messages/`

## MySQL storage model

- `apple_messages_source_sets`
- `apple_messages_messages`
- `apple_messages_participants`
- `apple_messages_conversations`
- `apple_messages_attachments`
- source observation records as required by the current schema

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

## Source handoff

- source pages and Muninn evidence derive from canonical message rows
- handoff scopes are resolved from canonical rows, not by rescanning `chat.db`

## Forbidden behavior

- no reinterpretation of missing contact data as truth

## Review checklist

- chronology is preserved
- message identity stays stable

## Acceptance checks

- Apple Messages can anchor timelines without fuzzy post-processing
