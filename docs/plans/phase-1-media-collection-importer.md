# Phase 1: Media Collection Importer

## Summary

Build the media collection importer last, using a real collection from `~/Projects/odinns/mostly-unique` once it is ready enough to inspect. First verify and tighten the media specs against the actual collection shape, then implement bounded indexing of references and metadata without copying binaries.

## Steps

1. Inspect the real collection and adjust the media specs before implementation.
2. Lock importer scope around collection roots, relative paths, metadata extraction, and sidecars if justified.
3. Add intake wiring for bounded root paths.
4. Implement canonical `media_collection_*` tables for roots, files, metadata, and optional sidecars.
5. Build the importer command and run recording.
6. Emit compile-facing handoff from canonical rows.
7. Test bounded traversal, rerun behavior, unreadable-file reporting, stale external reference detection, and metadata extraction.

## Acceptance

- Specs match the real collection before code is written.
- Imports references and metadata only, never copied binaries.
- Later scans may miss files temporarily or permanently without implying canonical deletion unless removal is an explicit, separately modeled fact.
- Traversal stays inside configured roots.
- Handoff is generated from canonical rows.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/media-collection-source-navigation.md`
- `docs/specifications/media-collection-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
