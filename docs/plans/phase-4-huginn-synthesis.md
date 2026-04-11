# Phase 4: Huginn Synthesis

## Summary

Build the downstream interpretive phase over canonical imports and Muninn evidence.

## Focus

- supported cross-source synthesis
- mandatory support traces
- explicit weak-evidence refusal
- correct `wiki/huginn/` output behavior

## Implementation Sequence

1. Lock the first Huginn scope around supported observations, patterns, and working models.
2. Select evidence from canonical rows and Muninn evidence bundles.
3. Validate generator output against evidence and output-shape rules before persistence.
4. Persist support traces for every non-trivial claim.
5. Compile Huginn output into `wiki/huginn/`.
6. Reject weak-evidence output instead of emitting sludge.

## Acceptance Scenarios

- supported synthesis across sources
- weak-evidence refusal
- support-trace persistence
- correct `wiki/huginn/` placement

## Specifications Used

- `docs/specifications/huginn-personality-pipeline.md`
- `docs/specifications/muninn-biography-pipeline.md`
- `docs/specifications/wiki-compilation-contract.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/ai-and-mcp-architecture.md`
- `docs/specifications/testing-and-tdd-strategy.md`

## Assumptions

- Canonical imports and Muninn evidence are stable enough to support challenged interpretation.
- Huginn stays downstream of evidence and does not rewrite source truth into certainty.
