# Facebook To Nornir Importer

## Goal

Import the biography-relevant Facebook archive slices into canonical MySQL tables for timeline and relationship use.

## Canonical source

Facebook export archive JSON under approved category roots.

## Inputs

- archive path
- optional category or thread scope filters later if needed
- dry-run or validate-only flags

## Output structure

- canonical `facebook_*` tables
- reviewable run mirrors under `data/runs/`
- artifacts under `data/imports/facebook/`

## MySQL storage model

- `facebook_archives`
- `facebook_people`
- `facebook_profile_snapshots`
- `facebook_social_edges`
- `facebook_threads`
- `facebook_messages`
- `facebook_message_observations`
- `facebook_reactions`
- `facebook_posts`
- `facebook_comments`
- `facebook_attachments`

## Data model

Preserve:

- profile identity snapshot
- people and social-edge relationships
- thread identity, participants, messages, and message reactions
- posts, comments, and archive reactions
- attachment references plus light filesystem metadata only

## Timestamp policy

- treat Facebook `timestamp` and `timestamp_ms` values as absolute UTC-backed epoch instants
- persist canonical datetime columns in UTC
- keep the original numeric epoch fields when they exist
- do not reinterpret Facebook timestamps through server-local or database-local timezone settings
- convert to local or viewer timezone only in downstream presentation layers

## Import rules

- traverse only approved biography-facing archive categories
- keep thread boundaries explicit
- preserve sender identity and timestamps
- normalize mojibake before persistence
- import attachment metadata without ingesting binaries
- record archive-local observations so reruns and later partial exports stay additive

## Incremental behavior

- rerun by archive identity plus stable canonical row identity
- later partial exports must not delete earlier valid history

## Validation

- profile snapshot presence when available
- thread and message counts
- participant joins
- social-edge counts
- post/comment/reaction counts
- attachment reference integrity
- timestamp conversion matches the source epoch values without local-time drift

## Wiki compilation handoff

- source pages and evidence bundles derive from MySQL rows for Messenger, profile, social graph, and authored activity

## Forbidden behavior

- no binary copying into canonical storage
- no relationship inference during import
- no Phase 1 drift into security, ads, preferences, or logged telemetry

## Review checklist

- approved categories stay explicit
- thread identity survives
- attachments remain references
- additive reruns do not depend on archive completeness
- repeated plumbing candidates are logged instead of abstracted on instinct

## Acceptance checks

- biography-facing Facebook history can support Muninn without archive rescans
