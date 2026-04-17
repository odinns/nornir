# Phase 3: Muninn Biography

## Summary

Build the first evidence-first biography phase after multiple source imports and the evidence workbench exist.

## Focus

- extraction of dated facts and observable events
- timeline shaping
- evidence bundling
- contradiction surfacing
- provenance-bound Muninn output only

## Implementation Sequence

1. Lock the first Muninn scope around event extraction, timeline assembly, and evidence bundles.
2. Select evidence from canonical imported rows and media metadata where relevant.
3. Build chronology-first extraction and shaping with provenance preserved throughout.
4. Surface contradictions instead of smoothing them away.
5. Persist Muninn evidence products first, and only compile `wiki/muninn/` pages when a durable page is worth keeping.
6. Record runs, artifacts, and provenance links for generated output.

## Acceptance Scenarios

- evidence-bound event extraction
- chronology ordering
- contradiction preservation
- traceable Muninn evidence output

## Specifications Used

- `docs/specifications/muninn-biography-pipeline.md`
- `docs/specifications/wiki-compilation-contract.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/ai-and-mcp-architecture.md`
- `docs/specifications/testing-and-tdd-strategy.md`

## Assumptions

- Multiple imported sources and bounded evidence-selection helpers already exist before this phase starts.
- Muninn output remains evidence-first and non-interpretive.
