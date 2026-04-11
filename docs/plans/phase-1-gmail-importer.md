# Phase 1: Gmail Importer

## Summary

Build the Gmail importer after the archive-heavy importers have stabilized the seam. Deliver bounded official-access or approved-export intake, canonical `gmail_*` tables, importer CLI, replay-safe additive reruns, run artifacts, and compile-facing handoff.

## Steps

1. Confirm the chosen source mode: official API, approved export, or both.
2. Lock the replayable scope contract around query or history cursor.
3. Add intake wiring for bounded Gmail source descriptors.
4. Implement canonical storage for accounts, threads, messages, labels, and attachment refs.
5. Build the importer command and run recording.
6. Emit compile-facing handoff from canonical rows.
7. Test happy path, additive rerun behavior, omitted-in-later-fetch-or-export behavior, malformed scope handling, and thread-label integrity.

## Acceptance

- Imports Gmail data through a bounded supported access mode.
- Replay scope is explicit and auditable.
- Later fetches or exports may omit older mail from the chosen scope without implying canonical deletion.
- Large binaries are not downloaded by default.
- Handoff is generated from canonical rows.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/gmail-source-navigation.md`
- `docs/specifications/gmail-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
