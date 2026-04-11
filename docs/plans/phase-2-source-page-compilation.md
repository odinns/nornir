# Phase 2: Source-Page Compilation

## Summary

Build the first real compiler stage after all non-blocked Phase 1 importers emit stable handoffs. If media collection is still blocked on `~/Projects/odinns/mostly-unique`, allow that as the only holdout. This phase is limited to source pages in `wiki/sources/`.

## Focus

- consume importer handoffs
- generate deterministic slugs and paths
- embed provenance
- overwrite reruns safely
- never write outside `wiki/`

## Implementation Sequence

1. Lock the compiler input shape from importer handoffs.
2. Define deterministic source-page path and slug rules from stable source identity.
3. Compile source pages from canonical rows only.
4. Embed run id and provenance references in every claim-bearing section.
5. Make reruns resolve to the same logical target and overwrite safely.
6. Reject generation when provenance is missing or incomplete.

## Acceptance Scenarios

- deterministic source-page path generation
- provenance-required failure cases
- rerun-safe overwrite of the same logical page
- no writes outside `wiki/`

## Specifications Used

- `docs/specifications/importer-framework.md`
- `docs/specifications/wiki-compilation-contract.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/storage-and-path-conventions.md`
- `docs/specifications/testing-and-tdd-strategy.md`

## Assumptions

- Media collection may remain the only blocked importer when this phase starts.
- Source pages compile from canonical rows and handoffs, not raw rescans.
