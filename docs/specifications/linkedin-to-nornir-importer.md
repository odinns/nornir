# LinkedIn To Nornir Importer

## Goal

Import biography-relevant LinkedIn export slices into canonical MySQL tables for timeline, reputation, recognition, networking, and message evidence.

## Canonical source

LinkedIn archive export directory under bounded local-path access.

Phase 1 is archive-only. No scraping. No API mode.

## Inputs

- archive path

## Output structure

- canonical `linkedin_*` tables
- reviewable run mirrors under `data/runs/`
- artifacts under `data/imports/linkedin/`

## MySQL storage model

- `linkedin_archives`
- `linkedin_people`
- `linkedin_profile_snapshots`
- `linkedin_positions`
- `linkedin_education_records`
- `linkedin_projects`
- `linkedin_skills`
- `linkedin_languages`
- `linkedin_connections`
- `linkedin_invitations`
- `linkedin_recommendations`
- `linkedin_endorsements`
- `linkedin_conversations`
- `linkedin_messages`
- `linkedin_message_attachments`
- `linkedin_shares`
- `linkedin_comments`
- `linkedin_reactions`
- `linkedin_rich_media`

## Data model

Preserve:

- one archive record per bounded export root
- one current profile snapshot per archive root
- dated career-history rows from positions, education, and projects
- recognition evidence from recommendations and endorsements
- network evidence from connections and invitations
- authored/public activity from shares, comments, reactions, and rich-media observations
- private human conversations and messages from `messages.csv`
- remote attachment URLs linked from messages as references only

Do not pretend the source gives a reliable global LinkedIn account id. In phase 1, archive identity is the stable import root.

## Timestamp policy

- parse each file according to its real timestamp format
- persist canonical datetime columns in UTC
- preserve original source timestamp strings where they carry audit value
- do not reinterpret source timestamps through server-local or database-local timezone settings
- convert to display-local time only downstream

## Import rules

- use file-specific extractors for supported CSV files
- import only biography and timeline material
- include endorsements because they are evidence of recognition, skills, and reputation over time
- include messages because they are biography-relevant correspondence
- keep recommendations and endorsements directional as given vs received observations
- keep `Rich_Media.csv` separate from shares unless a deterministic join exists
- store remote attachment and media URLs as references; do not download or mirror binaries
- fail with source-file context when a supported CSV has the wrong shape

## Incremental behavior

- rerun by archive identity plus stable canonical row identity
- messages rerun idempotently by deterministic row hash rather than import order
- later thinner exports must not delete earlier valid canonical rows
- additive reruns must preserve older biography evidence when a newer export omits it

## Validation

- validate the archive root exists and required phase-1 files are present
- validate `Connections.csv` contains a real header row after its preamble
- validate supported CSV rows do not overflow or break expected headers
- validate message identity and conversation grouping remain stable across reruns
- validate timestamp parsing per file family

## Source handoff

- source pages and evidence bundles derive from canonical rows only
- handoff is built from canonical `linkedin_*` rows, not by rescanning raw CSV files

## Forbidden behavior

- no scraping-first implementation
- no account-telemetry dump disguised as biography import
- no fake universal LinkedIn model built around nonexistent source ids
- no message attachment downloading
- no phase-1 drift into jobs, ads, security logs, search history, learning, or guide/coach system messages

## Review checklist

- supported files are explicitly allowlisted
- biography and timeline relevance drives scope
- endorsements remain in scope as recognition evidence
- `Connections.csv` preamble handling is explicit
- additive reruns do not depend on archive completeness
- shared plumbing stays shared, and LinkedIn quirks stay local

## Acceptance checks

- biography-relevant LinkedIn history can support Muninn without rescanning the raw export
- endorsements, recommendations, activity, and messages all land in canonical storage
- thinner later exports do not erase older valid biography rows
- LinkedIn phase 1 fits the existing importer framework instead of warping it
