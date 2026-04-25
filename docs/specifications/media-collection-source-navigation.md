# Media Collection Source Navigation

## Start here

The media collection is a set of photo and video archives spread across multiple external
drives. It has already been indexed by the `mostly-unique` tool into a MySQL database
called `monique`. Nornir reads from that database — it does not walk the filesystem. That companion project is not public yet.

## Canonical source

- `monique` MySQL database (read-only external connection)
- Tables: `volumes`, `directories`, `files`

## Source structure

```
volumes      — one row per physical drive (label, mount_path_last_seen, status)
directories  — full_path, basename, depth, parent_directory_id, volume_id
files        — basename, extension, normalized_file_type, size_bytes,
               created_at_fs, modified_at_fs, birth_at_fs,
               mime_type, duplicate_key, directory_id, volume_id
```

## Scope for Phase 1

Import files where `directories.full_path LIKE '/Volumes/%/Pictures/%'`.

This covers all volumes with a `Pictures` subtree. Other subtrees (Documents, Movies,
Music, etc.) are out of scope for Phase 1.

## Directory structure

```
/Volumes/{volume-label}/Pictures/
  {year}/
    {event-dir}/          ← basename often encodes date + label
      file.JPG
      file.MOV
      .picasa.ini         ← sidecar, not biographical
      file.THM            ← sidecar, not biographical
```

Event directory basenames are inconsistent. Known patterns:

| Pattern | Example |
|---------|---------|
| `YYYY-MM-DD Label` | `2009-04-29 Orlando - Impact Event` |
| `YYYY-MM Label` | `2010-01 January US trip` |
| `YYYY-MM` | `2012-03` |
| `YYYY-MM-DD` | `2007-03-24` |
| `YYYY-Mmm Label` | `2011-xxxx` |
| Label only | `Lesbos 2013`, `SD Kort 1 4GB` |
| Device name | `Sample iPhone 5`, `Family iPhone 4S` |
| iOS hash | `860OKMZO` |

Date extraction from basename is opportunistic. A null `event_date` is normal and not an error.

## File types

| `normalized_file_type` | What it means |
|------------------------|---------------|
| `image` | JPG, PNG, BMP, GIF, etc. |
| `video` | MOV, MPG, AVI, MP4, etc. |
| `null` | unrecognised — includes sidecars like `.ini`, `.THM`, `.rotate`, `.db`, `.jbf` |

Sidecars are not imported. Filter on `normalized_file_type IN ('image', 'video')`.

## Metadata availability

- `mime_type`: NULL for all indexed images in Phase 1 data
- `content_hash`: NULL — not computed yet
- `metadata_json` (EXIF): NULL — MetadataExtractor is a Phase 2 stub in mostly-unique
- `duplicate_key`: populated for most images — useful for dedup awareness but not authoritative

## Access rules

- Read-only connection to `monique`
- No filesystem traversal
- No binary copying into Nornir storage

## File basename signal

Most file basenames are camera-sequential and carry no useful information
(`P1120883.JPG`, `DSC_0001.JPG`, `IMG_2700.JPG`). A meaningful minority encode
real signal — people's names, event descriptions, or descriptive labels:

- `person-a-and-person-b-200x200.jpg`
- `portrait15.jpg`
- `midsommerfest 2010.png`

Basenames must be stored verbatim. The compilation layer is responsible for
deciding which carry useful signal. The importer does not filter or classify them.

macOS resource fork files (`._filename`) appear as sibling entries — they are
not biographical and should be excluded. Filter on `basename NOT LIKE '._%'`.

## Bottom line

Nornir bridges rows from `monique` into its own canonical `media_files` table, scoped
to Pictures directories. Files stay on external drives. The event directory name is the
primary date and event evidence; some file basenames add secondary signal. Dates are
approximate — never ground truth.
