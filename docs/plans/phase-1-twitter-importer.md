# Phase 1: Twitter Importer

## Summary

Build the Twitter archive importer for public-expression timeline data. Deliver bounded intake, canonical `twitter_*` tables, importer CLI, additive non-destructive reruns, run artifacts, and compile-facing handoff.

## Steps

1. Confirm the archive manifest and dataset files actually present.
2. Add or finish intake wiring for bounded archive paths.
3. Implement canonical storage for archives, tweets, media refs, profile snapshots, and interactions.
4. Build the importer command and run recording.
5. Emit compile-facing handoff from canonical rows.
6. Test happy path, additive rerun behavior, omitted-in-later-archive behavior, malformed dataset handling, and reply-link preservation where available.

## Acceptance

- Imports real Twitter archive data into canonical `twitter_*` tables.
- Later archives may omit older tweets or profile snapshots without implying canonical deletion.
- Tweet ids, timestamps, and reply/conversation linkage survive import.
- Media stays reference-only.
- Handoff is generated from canonical rows.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/twitter-source-navigation.md`
- `docs/specifications/twitter-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
