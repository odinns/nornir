# Phase 1: Importer Wave

## Summary

The importer wave is complete for the phase-1 sources tracked here: SMS, Facebook, Twitter, LinkedIn, Instagram, FidoNet, Gmail, and media collection. ChatGPT remains the earlier reference implementation. The remaining work now starts at bounded evidence access and biography-facing post-import tooling rather than more phase-1 importer completion.

## Importer Order

1. SMS
2. Facebook
3. Twitter
4. LinkedIn
5. Instagram
6. FidoNet
7. Gmail
8. Media collection

## Shared Completion Bar

Every importer track must end with:

- bounded intake
- canonical storage or justified boundary-preserving integration
- importer command
- additive, non-destructive reruns
- run/artifact recording
- compile-facing handoff
- real-data verification before an importer is considered done where source shape is uncertain

For every Phase 1 importer, idempotency means:

- repeated import of the same source must not duplicate canonical rows
- later exports, backups, or fetches may add new history
- later exports, backups, or fetches may omit older history
- omission in a later source must never be treated as canonical deletion
- canonical history must converge to the union of all valid observed source data, keyed by stable source identity where available
- importers may refresh better metadata or enrichment, but must not destroy earlier valid history just because a newer snapshot is incomplete

## Importer Tracks

- [phase-1-sms-importer.md](/Users/odinn/Projects/odinns/nornir/docs/plans/phase-1-sms-importer.md)
- [phase-1-facebook-importer.md](/Users/odinn/Projects/odinns/nornir/docs/plans/phase-1-facebook-importer.md)
- [phase-1-twitter-importer.md](/Users/odinn/Projects/odinns/nornir/docs/plans/phase-1-twitter-importer.md)
- [phase-1-linkedin-importer.md](/Users/odinn/Projects/odinns/nornir/docs/plans/phase-1-linkedin-importer.md)
- [phase-1-instagram-importer.md](/Users/odinn/Projects/odinns/nornir/docs/plans/phase-1-instagram-importer.md)
- [phase-1-fidonet-importer.md](/Users/odinn/Projects/odinns/nornir/docs/plans/phase-1-fidonet-importer.md)
- [phase-1-gmail-importer.md](/Users/odinn/Projects/odinns/nornir/docs/plans/phase-1-gmail-importer.md)
- [phase-1-media-collection-importer.md](/Users/odinn/Projects/odinns/nornir/docs/plans/phase-1-media-collection-importer.md)

## LinkedIn And Instagram Spec Verification

These two importer tracks must explicitly start by verifying the current specs against the real exports in `../llm-wiki/raw/history`, then adjusting the specs before implementation if needed:

- `docs/specifications/linkedin-source-navigation.md`
- `docs/specifications/linkedin-to-nornir-importer.md`
- `docs/specifications/instagram-source-navigation.md`
- `docs/specifications/instagram-to-nornir-importer.md`

Likely adjustments to call out in this phase:

- LinkedIn is broader than a thin milestone source because the export includes profile, positions, and messages; the importer plan must decide whether message history enters canonical scope now or is explicitly deferred.
- Instagram exports contain a lot of archive surface area; the importer plan must narrow canonical import to durable, useful authored/history data and explicitly ignore noise.
- Both specs stay provisional until checked against the actual files.

## Gmail And Media Tail

- Gmail is planned after the archive-heavy sources have stabilized the importer seam.
- Media collection starts with spec verification against the real collection in `~/Projects/odinns/mostly-unique` once that dataset is ready enough to inspect.
- If `mostly-unique` is not ready when the media track is reached, mark media as blocked on collection readiness rather than inventing a fake schema from thin air.
- Once available, the media track must verify and adjust these specs before implementation if needed:
  - `docs/specifications/media-collection-source-navigation.md`
  - `docs/specifications/media-collection-to-nornir-importer.md`

## FidoNet Boundary

- preserve its external canonical DB boundary
- derive only Nornir-owned helper or integration state
- do not fake a full-copy import for symmetry

## Acceptance Scenarios

- successful import from real available data for SMS, Facebook, Twitter, LinkedIn, Instagram, and FidoNet
- LinkedIn spec-verification step that confirms archive entities, then records required spec changes before implementation
- Instagram spec-verification step that confirms which archive datasets are genuinely useful and which are noise
- happy-path, additive-rerun, omitted-in-later-export, malformed-input, and handoff tests for every importer
- Gmail tests for bounded scope, replayable cursor or query handling, and provenance-safe import
- media collection spec-verification step against `~/Projects/odinns/mostly-unique` once ready
- media collection tests for bounded traversal, metadata extraction, unreadable-file reporting, and stale external reference detection
- FidoNet boundary test proving no fake full-copy import

## Status

Done. The tracked phase-1 importer set now has importer commands, canonical storage, rerun-safe imports, and compile-facing handoff coverage.

## Specifications Used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/storage-and-path-conventions.md`
- `docs/specifications/testing-and-tdd-strategy.md`
- `docs/specifications/sms-source-navigation.md`
- `docs/specifications/sms-to-nornir-importer.md`
- `docs/specifications/facebook-source-navigation.md`
- `docs/specifications/facebook-to-nornir-importer.md`
- `docs/specifications/twitter-source-navigation.md`
- `docs/specifications/twitter-to-nornir-importer.md`
- `docs/specifications/linkedin-source-navigation.md`
- `docs/specifications/linkedin-to-nornir-importer.md`
- `docs/specifications/instagram-source-navigation.md`
- `docs/specifications/instagram-to-nornir-importer.md`
- `docs/specifications/fidonet-source-navigation.md`
- `docs/specifications/fidonet-to-nornir-importer.md`
- `docs/specifications/gmail-source-navigation.md`
- `docs/specifications/gmail-to-nornir-importer.md`
- `docs/specifications/media-collection-source-navigation.md`
- `docs/specifications/media-collection-to-nornir-importer.md`

## Assumptions

- Phase 1 includes every importer spec currently planned for v1.
- LinkedIn and Instagram specs are provisional and must be verified against actual exports before implementation.
- Media collection is also provisional until `~/Projects/odinns/mostly-unique` is ready enough to inspect as a real source.
- The media importer should be planned from actual collection shape, not from decorative theory.
- Gmail does not currently need the same upfront spec-verification step unless implementation exposes a mismatch large enough to justify spec edits.
- The old ChatGPT importer is only a partial reference implementation until it is upgraded to the same additive, non-destructive import model.
- Each phase plan must reference only the specs needed for that phase, not the whole cathedral.
