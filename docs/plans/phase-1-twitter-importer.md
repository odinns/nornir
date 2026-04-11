# Phase 1: Twitter Importer

## Summary

Build the Twitter archive importer for public-expression timeline data from the real archive shape. Deliver bounded intake, explicit phase-1 dataset allowlisting, canonical `twitter_*` tables, importer CLI, additive non-destructive reruns, run artifacts, and compile-facing handoff.

## Steps

1. Parse `data/manifest.js` and the supported archive JS wrappers, then validate the phase-1 file allowlist against the real archive root.
2. Add or finish intake wiring for bounded archive paths.
3. Implement canonical storage for archives, accounts, profile snapshots, screen-name changes, tweets, note tweets, and media refs.
4. Import `tweets.js`, `community-tweet.js`, `note-tweet.js`, and supported account/profile files with explicit source-surface handling.
5. Build the importer command and run recording.
6. Emit compile-facing handoff from canonical rows only.
7. Test happy path, additive rerun behavior, omitted-in-later-archive behavior, malformed supported-file handling, optional-file absence, reply-link preservation, and media-reference validation.

## Acceptance

- Imports real Twitter archive data into canonical `twitter_*` tables.
- Later archives may omit older tweets or profile snapshots without implying canonical deletion.
- Tweet ids, timestamps, and reply/conversation linkage survive import.
- Community tweets share the canonical tweet surface but remain distinguishable by source surface.
- Note tweets import from their own archive shape without being flattened into normal tweets.
- Media stays reference-only.
- Likes, follower/following graph, DMs, Grok chat, ads, Spaces, and other non-phase-1 datasets stay out of scope even when present in the archive.
- Handoff is generated from canonical rows.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/twitter-source-navigation.md`
- `docs/specifications/twitter-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
