# Instagram To Nornir Importer

## Goal

Provide a future-safe importer for bounded Instagram archive data without pretending the source is broader or cleaner than it is.

## Canonical source

Supported archive export or official API response.

## Inputs

- bounded archive root path or API scope config
- dry-run or validate-only flags

For the current archive-backed phase-1 slice, the importer should expect and use these paths when present:

- `personal_information/personal_information/personal_information.json`
- `your_instagram_activity/media/posts_1.json`
- `your_instagram_activity/media/profile_photos.json`
- `your_instagram_activity/media/stories.json` as optional input
- `media/` for archive-relative binary references declared by accepted JSON payloads

## Output structure

- canonical `instagram_*` tables
- run artifacts under `data/imports/instagram/`

## MySQL storage model

- `instagram_accounts`
- `instagram_posts`
- `instagram_media_refs`
- `instagram_profile_snapshots`

## Data model

Store only entities actually available from the supported source mode.

For phase 1 this means:

- one account record for the imported archive identity
- profile snapshot records extracted from the personal information payload
- post records derived from archive post metadata
- media ref records linked from profile photos, posts, and optional stories

Do not widen the phase-1 data model to include every archive surface just because the export contains it.

## Import rules

- archive-first when possible
- API second when stable and permitted
- handle sparse or tiny datasets gracefully
- use file-specific extractors for known archive slices instead of a generic archive parser
- import account metadata, profile snapshot fields, post captions, timestamps, and media refs from the accepted phase-1 files
- treat `stories.json` as optional and skip it cleanly when absent
- store archive-relative media references as references; do not copy source binaries into git-tracked locations
- defer messages, comments, likes, followers, following, login history, ads, contacts, and other secondary archive surfaces to later phases
- malformed file shapes must fail with source-path context rather than being silently ignored

## Incremental behavior

- rerun by account and stable source identity where available
- posts should rerun idempotently by stable archive occurrence identity rather than import-run order
- profile snapshots and media refs should tolerate sparse reruns without manufacturing missing data

## Validation

- source capability detection
- schema-shape validation for the chosen source mode
- validate the accepted archive root shape before import
- validate that required phase-1 files exist when claiming full archive support
- allow partial archive import when only the supported profile and post slices are present
- validate referenced media paths relative to the accepted archive root and report missing paths clearly

## Wiki compilation handoff

- produce source pages and timeline-supporting evidence only when data exists

For phase 1, handoff should be limited to imported account/profile and post evidence only.

## Forbidden behavior

- no scraping-first implementation
- no fake completeness when the source data is thin
- no messages importer smuggled into phase 1
- no giant switchboard that tries to normalize every Instagram archive category at once

## Review checklist

- bounded access mode is explicit
- sparse data is treated honestly
- accepted phase-1 files are named explicitly
- deferred archive surfaces are called out explicitly
- media refs are stored as references rather than copied assets

## Acceptance checks

- Instagram phase 1 can be implemented from this spec without inventing source policy
- Instagram can be added later without redesigning the importer framework
- the importer can process a sparse archive containing only profile and post slices
