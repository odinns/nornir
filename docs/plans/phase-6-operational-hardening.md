# Phase 6: Operational Hardening

## Summary

Finish post-pipeline cleanup without turning this phase into a junk drawer.

## Focus

- fixing flaky MySQL test resets
- improving operator-facing rerun messaging
- strengthening architecture tests around handoff and provenance seams
- deleting abstractions that started getting ideas above their station

## Implementation Sequence

1. Stabilize MySQL test database resets for isolated runs.
2. Improve rerun and failure messaging in commands and run records.
3. Add architecture tests around importer-to-compilation and evidence boundaries.
4. Remove or simplify abstractions that drifted into framework cosplay.
5. Run the full quality gates and close the obvious rough edges.

## Acceptance Scenarios

- isolated test DB reset reliability
- clearer rerun diagnostics
- architecture tests guarding handoff and provenance seams

## Specifications Used

- `docs/specifications/importer-framework.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
- `docs/specifications/coding-standards-and-quality-gates.md`

## Assumptions

- This phase is cleanup after the pipeline exists, not a place to smuggle in new product work.
