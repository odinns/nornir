# Phase 1: LinkedIn Importer

## Summary

Build the LinkedIn importer from the real export in `../llm-wiki/raw/history/linkedin`. The phase-1 slice is biography and timeline material: profile history, recognition, networking, activity, and private human messages.

## Steps

1. Validate archive shape and accepted phase-1 files.
2. Import profile and career-history slices.
3. Import relationship and recognition slices, including recommendations and endorsements.
4. Import activity slices.
5. Import human private messages and remote attachment refs.
6. Emit compile-facing handoff from canonical rows.
7. Test idempotence, sparse reruns, malformed CSV handling, and timestamp parsing.

## Acceptance

- Imports biography-relevant LinkedIn entities into canonical `linkedin_*` tables.
- Endorsements are included because they contribute to biography and timeline evidence.
- `messages.csv` is included in phase 1.
- `Connections.csv` preamble handling is explicit and tested.
- Later thinner exports do not erase older valid canonical rows.
- Handoff is generated from canonical rows without rescanning raw source material.

## Specifications Used

- `docs/specifications/importer-framework.md`
- `docs/specifications/linkedin-source-navigation.md`
- `docs/specifications/linkedin-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
