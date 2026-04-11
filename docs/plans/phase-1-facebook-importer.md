# Phase 1: Facebook Importer

## Summary

Build the Facebook archive importer for the core biography slice: Messenger, profile, social graph, posts, comments, reactions, and attachment references. Deliver bounded intake, canonical `facebook_*` tables, importer CLI, additive non-destructive reruns, run artifacts, compile-facing handoff, and a log of shared-plumbing extraction candidates.

## Steps

1. Confirm the biography-facing archive layout across `personal_information/`, `connections/`, Messenger, posts, and comments/reactions.
2. Add or finish intake wiring for bounded archive paths.
3. Implement canonical storage for archives, people, profile snapshots, social edges, threads, messages, authored activity, reactions, and attachment refs.
4. Build the importer command and run recording.
5. Emit compile-facing handoff from canonical rows.
6. Test happy path, additive rerun behavior, omitted-in-later-archive behavior, malformed archive handling, mojibake normalization, and attachment-reference preservation.
7. Log repeated plumbing patterns for later extraction without broadening this phase.

## Acceptance

- Imports real Facebook archive data for the approved biography slice into canonical `facebook_*` tables.
- Later partial archives do not delete older messages or threads already observed canonically.
- Thread identity, authored activity timestamps, and social-edge types survive import.
- Attachment data stays reference-only with light metadata only.
- Handoff is generated from MySQL rows, not archive rescans.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/facebook-source-navigation.md`
- `docs/specifications/facebook-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
