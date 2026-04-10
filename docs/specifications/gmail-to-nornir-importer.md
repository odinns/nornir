# Gmail To Nornir Importer

## Goal

Import Gmail messages and threads into canonical MySQL tables through bounded official access.

## Canonical source

Gmail API results or an explicitly approved export equivalent.

## Inputs

- account configuration
- query or history scope
- dry-run or validate-only flags

## Output structure

- canonical `gmail_*` tables
- reviewable run mirrors under `data/runs/`
- run artifacts under `data/imports/gmail/`

## MySQL storage model

- `gmail_accounts`
- `gmail_threads`
- `gmail_messages`
- `gmail_message_labels`
- `gmail_attachments`

## Data model

Preserve Gmail message ids, thread ids, labels, normalized text, and attachment references.

## Import rules

- import by bounded scope
- preserve headers and canonical ids
- do not download large binaries by default
- preserve the scope snapshot or history cursor that made the fetch replayable

## Incremental behavior

- rerun by Gmail ids and sync scope

## Validation

- thread-message joins
- label consistency
- scope accounting

## Wiki compilation handoff

- source pages compile from canonical Gmail rows

## Forbidden behavior

- no scraping
- no unbounded mailbox traversal

## Review checklist

- official access path is respected
- scope is explicit

## Acceptance checks

- Gmail can be imported incrementally without losing provenance
