# Importer Framework

## Purpose

Define how source-specific importers plug into Nornir without collapsing into one mega-command.

## Responsibilities

- provide one importer per source
- share only plumbing
- standardize run recording, validation, and provenance hooks

## Inputs

- intake handoff payloads
- source-local configuration
- source-local dry-run and validation mode flags when implemented

## Outputs

- canonical normalized rows in MySQL
- run records
- artifacts under `data/imports/` and `data/runs/`
- bounded source/evidence handoff payloads

## Storage and state

- shared infrastructure tables for runs and artifacts
- source-prefixed tables for canonical normalized content
- optional derived importer tables where justified

## Processing rules

- one command or action per source
- shared flags mean the same thing across sources
- source-specific flags stay local
- reruns must be idempotent
- logical identity and source occurrence identity must be distinguished where needed
- canonical rows that can reappear across source-set imports must have source-set observation rows
- handoff builders must scope reappearing canonical rows through observations, not `first_seen_*` metadata or account-wide inference
- `first_seen_*` columns are historical metadata only; they are not handoff boundaries
- observation rows should record their source, defaulting to `import`, so replay-created coverage can be distinguished later if needed
- importer-specific diagnostics belong under `data/imports/<source>/`
- shared operational summaries and auditable run mirrors belong under `data/runs/`
- importer-owned canonical datetime fields must be written as UTC instants
- timezone conversion for human display belongs downstream, not inside canonical import persistence

## Forbidden behavior

- no fake universal source model
- no importer writes directly into Huginn or Muninn markdown
- no source-specific branching monolith

## Interfaces

- source-scoped import command
- shared run recorder
- validation result contract
- source handoff contract

### Source handoff contract

An importer handoff must identify:

- source type
- canonical row set or scope to compile from
- source-set ids that bound the handoff
- whether the handoff is for source pages, evidence bundles, review artifacts, or a narrower downstream slice
- the owning run id

When canonical rows are deduped across imports, the handoff scope must come from source-set observations. A later thinner export should not inherit older rows just because they share an account, archive family, or `first_seen_*` value.

## Validation and testing

- every importer gets feature and unit tests
- rerun behavior is mandatory to test
- malformed inputs must surface clearly

## Review checklist

- shared code is actually shared plumbing
- source logic lives with the source
- import and compilation remain separate

## Acceptance checks

- adding a new importer does not require editing a giant switchboard
