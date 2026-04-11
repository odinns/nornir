# Phase 1: Facebook Importer

## Summary

Build the Facebook archive importer for Messenger-style thread history. Deliver bounded intake, canonical `facebook_*` tables, importer CLI, additive non-destructive reruns, run artifacts, and compile-facing handoff.

## Steps

1. Confirm the archive thread layout and message file shape.
2. Add or finish intake wiring for bounded archive paths.
3. Implement canonical storage for archives, threads, participants, messages, reactions, and attachment refs.
4. Build the importer command and run recording.
5. Emit compile-facing handoff from canonical rows.
6. Test happy path, additive rerun behavior, omitted-in-later-archive behavior, malformed archive handling, and attachment-reference preservation.

## Acceptance

- Imports real Facebook archive data into canonical `facebook_*` tables.
- Later partial archives do not delete older messages or threads already observed canonically.
- Thread identity and timestamps survive import.
- Attachment data stays reference-only.
- Handoff is generated from MySQL rows, not archive rescans.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/facebook-source-navigation.md`
- `docs/specifications/facebook-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
