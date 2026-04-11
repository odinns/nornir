# Phase 1: FidoNet Importer

## Summary

Build the FidoNet importer against the existing canonical FidoNet database. Preserve that boundary, derive only Nornir-owned `fidonet_*` helper state, and deliver importer CLI, rerun-safe integration state, run artifacts, and compile-facing handoff.

## Steps

1. Confirm the canonical database access shape and bounded source scope.
2. Lock which derived cleanup and thread projections Nornir will own.
3. Add or finish intake wiring for source descriptors and scope.
4. Implement `fidonet_*` integration and helper tables only where justified.
5. Build the importer command and run recording.
6. Emit compile-facing handoff from canonical references plus derived views.
7. Test connectivity, rerun behavior, thread integrity, and no-fake-full-copy behavior.

## Acceptance

- Preserves the external canonical database boundary.
- Stores only justified Nornir-owned derived or integration state.
- Reruns are safe by canonical ids and source scope without treating later narrower visibility as deletion.
- Handoff does not depend on a fake mirrored import.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/fidonet-source-navigation.md`
- `docs/specifications/fidonet-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
