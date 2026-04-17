# Phase 2: Evidence Workbench

## Summary

Build the first post-import layer around bounded evidence access, inspection, and biography-facing extraction helpers. Do not force every importer slice through a markdown compiler before the workflow is proven. If media collection is still blocked on `~/Projects/odinns/mostly-unique`, allow that as the only holdout.

If the handoff layer still feels abstract, read `docs/handoff-explainer.md` first. In this phase the handoff is a bounded query contract, not the centerpiece of a source-page ritual.

## Focus

- consume bounded importer slices directly from canonical rows
- expose evidence-selection and inspection helpers for manual and AI-assisted work
- support biography-facing extraction without raw rescans
- produce structured review artifacts when useful
- keep durable markdown output optional and narrow

## Implementation Sequence

1. Lock the first bounded evidence-query interface over canonical imports and source sets.
2. Build source-aware selectors for chronology-relevant and biography-relevant slices.
3. Produce structured evidence bundles or review artifacts from canonical rows only.
4. Preserve run id and provenance links throughout every extracted bundle or summary.
5. Keep markdown generation optional and explicit; only emit durable pages when the output is worth preserving.
6. Reject extraction or generation when the bounded slice or provenance is incomplete.

## Acceptance Scenarios

- bounded evidence selection from canonical rows
- chronology-relevant extraction without raw rescans
- provenance-required failure cases
- reviewable structured output for a bounded slice
- optional durable output stays deterministic when emitted

## Specifications Used

- `docs/specifications/importer-framework.md`
- `docs/specifications/muninn-biography-pipeline.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/wiki-compilation-contract.md`
- `docs/specifications/testing-and-tdd-strategy.md`

## Assumptions

- Media collection may remain the only blocked importer when this phase starts.
- Canonical imports and bounded slices are the real foundation; markdown is a downstream convenience, not the primary product of this phase.
