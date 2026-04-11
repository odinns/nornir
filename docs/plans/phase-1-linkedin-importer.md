# Phase 1: LinkedIn Importer

## Summary

Build the LinkedIn importer from the real export in `../llm-wiki/raw/history/linkedin`. First verify and tighten the current specs against actual CSV files, then implement bounded intake, canonical `linkedin_*` tables, importer CLI, additive non-destructive reruns, run artifacts, and compile-facing handoff.

## Steps

1. Inspect the real export and adjust the LinkedIn specs before implementation.
2. Lock importer scope around profile snapshots, positions, organizations, and posts or messages only if explicitly kept in scope.
3. Add or finish intake wiring for bounded archive paths.
4. Implement canonical storage for the locked scope.
5. Build the importer command and run recording.
6. Emit compile-facing handoff from canonical rows.
7. Test happy path, additive rerun behavior, omitted-in-later-export behavior, malformed CSV handling, and date-range preservation.

## Acceptance

- Specs match the real export shape before code is written.
- Imports the approved LinkedIn entities into canonical `linkedin_*` tables.
- Later exports may omit older profile or message data without implying deletion of earlier valid canonical history.
- Sparse or missing datasets are handled honestly.
- Handoff is generated from canonical rows.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/linkedin-source-navigation.md`
- `docs/specifications/linkedin-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
