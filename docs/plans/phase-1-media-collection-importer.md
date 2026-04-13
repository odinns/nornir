# Phase 1: Media Collection Importer

## Summary

Bridge photo and video records from the `monique` MySQL database (mostly-unique) into
Nornir's canonical `media_files` table. Scope is `/Volumes/*/Pictures/` across all
indexed volumes. The primary biographical signal is the event directory name — it
encodes date and event label. EXIF is not yet available in the source.

## Source

`monique` MySQL database (db: `monique`, host: `127.0.0.1`, user: `root`). Already
indexed by mostly-unique. Nornir reads it via a second DB connection — no filesystem
traversal.

Key tables in monique: `volumes`, `directories`, `files`.

**Verified counts (from live monique, 2026-04-13):**

| Volume | Images under Pictures | Date range (fs_created_at) |
|--------|-----------------------|---------------------------|
| Macintosh HD - Data | 159,691 | 2009–2026 |
| Odinns 2TB | 74,147 | 2016–2020 |
| LIMA-2 | 73,294 | 2016 |
| OS | 44,033 | 2013 |
| DATA | 4,059 | 2009 |

Total scope: ~205,540 files, of which ~195,533 images and ~4,540 videos.
The remaining ~4,259 are unknown type — mostly sidecars, excluded by type filter.

> Note: `fs_created_at` on LIMA-2 and Odinns 2TB clusters around 2016 because that
> is when the drive was indexed, not when photos were taken. `fs_modified_at` is more
> reliable as a photo date proxy where EXIF is unavailable.

## What matters in Phase 1

- **Event directory basename** — primary carrier of date and event label
  - Patterns observed in real data: `YYYY-MM-DD Label`, `YYYY-MM Label`, `YYYY-MM-DD`,
    `YYYY-MM`, label-only, device name, iOS hash (`860OKMZO`)
  - Date parsed opportunistically; null is normal and not an error
  - Examples from real data: `2009-04-29 Orlando - Impact Event`,
    `2010-01 January US trip`, `2013-08 Island`, `Lesbos 2013`, `Odinns iPhone 5`
- **File basename** — secondary signal; most are camera-sequential (`P1120883.JPG`,
  `DSC_0001.JPG`, `IMG_2700.JPG`) and carry nothing, but a meaningful minority encode
  names, events, or descriptions. Real examples found in data:
  - `rhondacort-and-odinnsorensen-200x200.jpg`
  - `podinn15.jpg`, `podinn24.jpg` (from `2005/Eriks billeder/20050402`)
  - `midsommerfest 2010.png`
  Store verbatim; do not discard or truncate.
- **Volume label** — identifies which physical drive
- **File type** — image or video (filter out sidecars via `normalized_file_type`)
- **File size and timestamps** — from monique `files` table

**Fields confirmed null across all indexed images:** `mime_type`, `content_hash`,
`metadata_json` (EXIF). MetadataExtractor in mostly-unique is a Phase 2 stub.
Do not design around these for Phase 1.

**`duplicate_key`** is populated on most images — monique uses it for dedup tracking.
Store it in canonical rows for future use; do not act on it in Phase 1.

## Canonical table

`media_files` — one row per image/video file, keyed on `source_file_id` (monique `files.id`).

Columns: `source_file_id`, `volume_label`, `volume_mount_path`, `directory_full_path`,
`event_label`, `event_date`, `basename`, `extension`, `normalized_file_type`,
`size_bytes`, `fs_created_at`, `fs_modified_at`, `duplicate_key`, timestamps.

No separate roots, metadata, or sidecars tables.

> The original spec imagined four tables (roots, files, metadata, sidecars) and inline
> EXIF extraction. Inspection of the real data ruled this out: metadata is universally
> null, sidecars are already separated by `normalized_file_type`, and a roots table
> adds no value over a `volume_label` column.

## Implementation steps

1. Migration — `create_media_files_table`
2. `ImportMediaCollectionAction` — reads from monique via paginated query (500/page),
   writes canonical rows via `SourceObservationStore::upsertAndReturnId`
3. `import:media-collection` command — `{--source-dsn=}`, `{--volume=}`, `{--dry-run}`
4. `BuildMediaSourcePageHandoffAction` + `handoff:media-source-pages` command
5. Tests: happy path, volume restriction, idempotent rerun, missing source connection

## Date parsing

Cascade applied to `event_label` (event directory basename):

| Pattern | Extracted date |
|---------|---------------|
| `^\d{4}-\d{2}-\d{2}` | full date |
| `^\d{4}-\d{2}(?!\d)` | first of month |
| `^\d{4}(?!\d)` | 1 Jan of year |
| no match | null |

All parsed dates are approximate — Muninn must not treat them as ground truth.

> The date pattern is not universally followed. Real directory names include
> `2011-xxxx`, `SD Kort 1 4GB`, `860OKMZO` (iOS hash), and `Lesbos 2013` (year
> embedded mid-string). The cascade handles the common cases; everything else gets null.

## Sidecars

Excluded by the `normalized_file_type IN ('image', 'video')` filter. From inspection
of LIMA-2/Pictures: `.ini` (207), `.THM` (70), `.rotate` (17), `.db` (8), `.jbf` (6)
are the common sidecar types. All have `normalized_file_type = NULL` in monique and
are filtered out without special-casing.

Also exclude macOS resource fork shadow files: `basename NOT LIKE '._%'`.

## TDD order

1. Migration — tables exist with correct columns and unique constraint on `source_file_id`
2. `ImportMediaCollectionAction` — happy path (2 volumes, mixed files), idempotent rerun,
   volume restriction, unreachable source DB
3. `import:media-collection` command — output strings, DB state
4. `BuildMediaSourcePageHandoffAction` — canonical scope shape
5. `handoff:media-source-pages` command

## Acceptance

- Row count matches monique image+video count under Pictures scope (~200k)
- Rerun against same monique snapshot produces zero new inserts
- `--volume=LIMA-2` imports only that volume (~12k files in Pictures branch)
- `event_date` null for dirs without recognisable date prefix, not an error
- Unreachable monique connection fails with a clear error message
- Handoff emits correct `sourceType: 'media-collection'`

## Out of scope for Phase 1

- EXIF extraction (MetadataExtractor is a stub in mostly-unique — not available)
- Content hash / deduplication decisions (`duplicate_key` stored but not acted on)
- Sidecar content parsing (`.picasa.ini` etc.)
- Subtrees other than Pictures (Business, Music, Documents — separate phases)
- Filesystem traversal

## Specifications used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/media-collection-source-navigation.md`
- `docs/specifications/media-collection-to-nornir-importer.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/testing-and-tdd-strategy.md`
