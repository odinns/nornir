# Phase 1: SMS Importer

## Summary

Build the SMS importer as the first chronology-heavy source after ChatGPT. Deliver bounded intake, canonical `sms_*` tables, importer CLI, additive non-destructive reruns, run artifacts, and compile-facing handoff.

## Steps

1. Confirm the actual SMS export shape and stable message identity.
2. Add or finish intake wiring for bounded SMS source paths.
3. Implement canonical storage for messages, participants, conversations, and attachments.
4. Build the importer command and run recording.
5. Emit compile-facing handoff from canonical rows.
6. Test happy path, additive rerun behavior, omitted-in-later-backup behavior, malformed input, and provenance-safe handoff.

## Acceptance

- Imports real SMS data into canonical `sms_*` tables.
- Reruns are idempotent by source-set and message identity without deleting older canonical history.
- Newer incomplete backups do not remove older messages already observed canonically.
- Conversation grouping stays derived and reproducible.
- Handoff is generated without rescanning raw input.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/sms-source-navigation.md`
- `docs/specifications/sms-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
