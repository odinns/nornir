# Media Collection To Nornir Importer

## Goal

Bridge photo and video records from the `monique` MySQL database into Nornir's canonical
`media_files` table so they can support biographical timeline evidence without copying
the files or re-scanning the filesystem.

## Source

`monique` MySQL database (read-only). Connection details come from the `--source-dsn`
flag or a named connection in `config/database.php`.

## Scope

Files in `monique` where:
- `directories.full_path LIKE '/Volumes/%/Pictures/%'`
- `files.normalized_file_type IN ('image', 'video')`
- `files.basename NOT LIKE '._%'` — exclude macOS resource fork shadow files

Sidecars (`.ini`, `.THM`, `.rotate`, `.db`, `.jbf`) are excluded by the type filter.
Resource forks (`._`) are excluded by the basename filter.

## Inputs

```
import:media-collection
  {--volume=}      Volume label to restrict import (e.g. LIMA-2). Omit = all volumes.
  --source-dsn=    Named DB connection or DSN for monique (required)
  {--dry-run}      Report counts without writing
  {--validate-only} Verify connection and scope without importing
```

## Canonical storage

Single table: `media_files`

```sql
media_files
  id                     bigint unsigned PK
  source_file_id         bigint unsigned NOT NULL   -- monique files.id
  volume_label           varchar(255) NOT NULL      -- monique volumes.label
  volume_mount_path      varchar(500)               -- volumes.mount_path_last_seen
  directory_full_path    text NOT NULL              -- directories.full_path
  event_label            varchar(500)               -- basename of event dir (depth below Pictures root)
  event_date             date NULL                  -- parsed from event_label, null if unrecognisable
  basename               varchar(255) NOT NULL
  extension              varchar(50)
  normalized_file_type   varchar(50)                -- 'image' or 'video'
  size_bytes             bigint unsigned
  fs_created_at          timestamp NULL
  fs_modified_at         timestamp NULL
  duplicate_key          varchar(255)               -- from monique, for dedup awareness
  timestamps
  UNIQUE (source_file_id)
```

No separate roots, metadata, or sidecars tables in Phase 1.
EXIF metadata is not available in the source and is not extracted.

## Identity and idempotency

Stable key: `source_file_id` (monique `files.id`). Reruns call `updateOrInsert` on this
key — safe to rerun against the same or an updated monique snapshot.

## Date parsing

Extract `event_date` from `event_label` using a cascade of patterns applied to the
basename of the directory one level below the year directory:

| Regex | Result |
|-------|--------|
| `^\d{4}-\d{2}-\d{2}` | full date |
| `^\d{4}-\d{2}(?!\d)` | first day of month |
| `^\d{4}(?!\d)` | 1 Jan of year |

If no pattern matches, `event_date` is NULL. All parsed dates are approximate;
Muninn must not treat them as ground-truth timestamps.

## Run artifacts

Written under `data/imports/media-collection/`. Summary includes:
- `volume_label` (or `all`)
- `files_inspected`, `files_imported`, `files_reobserved`
- `volumes` list

## Provenance

One `ProvenanceWriter::link()` call per file with:
- `outputTarget`: `media_files:{source_file_id}`
- `claimKey`: `imported-media-file`
- `evidenceType`: `db-row`
- `evidenceRef`: `monique#files:{source_file_id}`

## Handoff

`handoff:media-source-pages {--run-id=}` emits a `WikiCompilationHandoffData` with
`sourceType: 'media-collection'` and canonical scope including row counts and volume list.

## Import rules

- Read from `monique` via paginated DB query (order by `files.id`, page 500 at a time)
- Write to Nornir `media_files` with `updateOrInsert` on `source_file_id`
- Report progress per page: `files_imported`, `files_reobserved`

## Forbidden behavior

- No filesystem traversal
- No binary copying
- No EXIF extraction (not available in source data)
- No claiming `event_date` is authoritative — it is directional evidence only

## Acceptance checks

- Rows match count of images+videos in monique under Pictures scope
- Rerun with same monique snapshot produces identical counts with zero new inserts
- Volume restriction (`--volume=LIMA-2`) imports only that volume's files
- Missing or unreachable `monique` connection fails gracefully with a clear error
- `event_date` is null for dirs without a recognisable date prefix, not an error
