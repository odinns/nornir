# Phase 1: Instagram Importer

## Summary

Build the Instagram importer from the real export in `../llm-wiki/raw/history/instagram`. First verify and tighten the current specs against actual JSON datasets, then implement bounded intake, canonical `instagram_*` tables, importer CLI, additive non-destructive reruns, run artifacts, and compile-facing handoff.

## Steps

1. Inspect the real export and adjust the Instagram specs before implementation.
2. Lock importer scope around durable authored/history data and explicitly ignore noisy or low-value datasets.
3. Add or finish intake wiring for bounded archive paths.
4. Implement canonical storage for the locked scope.
5. Build the importer command and run recording.
6. Emit compile-facing handoff from canonical rows.
7. Test happy path, additive rerun behavior, omitted-in-later-export behavior, sparse-data handling, and bounded archive traversal.

## Acceptance

- Specs match the real export shape before code is written.
- Imports only approved Instagram entities into canonical `instagram_*` tables.
- Later exports may be incomplete; omissions must not erase earlier valid canonical history.
- Sparse data does not force fake completeness.
- Handoff is generated from canonical rows.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/instagram-source-navigation.md`
- `docs/specifications/instagram-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
