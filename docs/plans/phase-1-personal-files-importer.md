# Phase 1: Personal Files Importer

## Summary

Index personal files from `monique` as secondary biographical evidence — documents,
music, recordings, and curated desktop artifacts. These don't have the structured
date/event naming of the Pictures tree or the project framing of the Business tree,
but they carry identity signal: taste (music collection), life administration
(Documents), cultural attachments (Digterstemmer, tracker music), and early-era
computing (gammel-disk).

Same `monique` DB bridge as the media and IM arc importers. Read-only.

---

## In scope

**Verified from monique (2026-04-13), all on LIMA-2:**

| Folder | Files | What's there |
|--------|-------|-------------|
| `Sophie_C/gamle diske/gammel-disk/` | ~30,122 | Odinn's old DOS/Windows machine from early–mid 1990s. See note below. |
| `Documents/` | 826 | Flame arts org, Essex apartment, Jasmin school records, GoldED ARJ archives, Camtasia screencasts, conference call MP3s |
| `Music/` | 11,465 | Artist/album tree plus large MOD/S3M/XM/IT tracker collection |
| `Recordings/` | 53 | Audio files — likely screen recordings or call captures |
| `Desktop/` | 126 | `Digterstemmer/` — numbered Danish spoken poetry MP3s |
| `Private/` | 1,545 | MP3 (736), WMA (128), PDF (113), video, archives |
| `Downloads/Bruce/` | 206 | Likely Bruce C. Fein materials (collaborator from IM arc) |
| `Downloads/DR_P3_Source_11/` | 127 | Danish Radio P3 source — media/cultural evidence |
| `Downloads/HTC Desire microSD kort/` | 196 | Phone contents from HTC Desire era |

---

## gammel-disk: early tech era archive

`Sophie_C/gamle diske/gammel-disk/` — despite the location under Sophie_C, this is
Odinn's own old DOS machine backup, confirmed by the tooling present. It is the
primary evidence for the pre-FidoNet and FidoNet-era technical life arc.

**Verified subdirectories and what they mean:**

| Subdir | Files | Signal |
|--------|-------|--------|
| `fileserver` | 6,984 | Local fileserver contents — shared files |
| `Programmer` | 5,712 | Installed programs (DOS/Windows era) |
| `source` | 4,516 | Personal source code — 4,500+ files of Odinn's own programming work |
| `BC5` | 3,374 | Borland C++ 5 — primary IDE/compiler |
| `usr` | 3,282 | Unix-style user directory |
| `djgpp` | 2,249 | DJGPP — GCC port for DOS; serious C/C++ development |
| `watcom` | 770 | Watcom C/C++ compiler — another serious tool |
| `amipro` | 409 | AmiPro word processor |
| `ter400` | 382 | TER400 — unknown |
| `Program Files` | 342 | Windows program files |
| `Download` | 333 | Downloaded files |
| `ZyXEL` | 268 | ZyXEL modem software — dial-up era |
| `gstools` | 263 | GoldED/FidoNet support tools |
| `FrontPage Webs` | 153 | Microsoft FrontPage — early web work |
| `Improv` | 128 | Lotus Improv spreadsheet |
| `zoc` | 101 | **ZOC terminal emulator** — the primary tool for FidoNet and BBS access |
| `LOGITECH` | 71 | Mouse software |
| `MSWorks` | 70 | Microsoft Works |
| `HOMEBANK` | 59 | Personal finance software |
| `LANMAN.DOS` | 52 | LAN Manager for DOS — networked even then |

> `zoc/` and `gstools/` together confirm this machine was the FidoNet workstation.
> ZOC was a leading terminal emulator for FidoNet node operators. `gstools` are
> GoldED support tools. Combined with the GoldED ARJ source archives in Documents/
> and the Fidonet importer data already in Nornir, this machine is the physical root
> of the FidoNet arc.
>
> `source/` with 4,516 files is significant — this is original code, not purchased
> software. What it contains is not yet known. Muninn should treat it as evidence
> of active programming at this period.
>
> `BC5` + `DJGPP` + `Watcom` is more compiler tooling than a casual user would have.
> This is a developer's machine.

---

## Jasmin-related materials

Jasmin is Odinn's daughter. Sophie is Jasmin's mother and Odinn's ex-wife. Jasmin's
presence threads through multiple folders and is parenting-arc evidence — school years,
musical taste, devices owned:

| Location | Signal |
|----------|--------|
| `Documents/Jasmin Skole/` | School materials — age/grade anchors the parenting timeline |
| `Music/Jasmins musik/` | Her music collection — taste, age, mobile subfolder implies device handoff |
| `Pictures/.../Billeder af Jasmin*` | Photos — covered in media collection importer |
| `Pictures/.../Jasmins iPhone 4S/` | Her phone photos — covered in media collection importer |

Import under their natural `folder_scope` without special-casing — the Jasmin
connection is visible from `collection_path`.

---

## Music collection breakdown

**Verified from monique (LIMA-2/Music/, 11,465 files):**

| Extension | Count | What |
|-----------|-------|------|
| mp3 | 5,498 | Standard audio |
| mod | 1,368 | ProTracker/FastTracker — demoscene format |
| jpg | 1,328 | Album art |
| m4a | 1,095 | iTunes purchases/rips |
| s3m | 618 | ScreamTracker 3 — demoscene format |
| au | 316 | Sun audio format |
| mid | 293 | MIDI files |
| xm | 257 | FastTracker 2 — demoscene format |
| wma | 199 | Windows Media Audio |
| it | 89 | ImpulseTracker — demoscene format |

**Known artists/albums (from directory listing):**
DJ Tiësto (Elements of Life, In Search of Sunrise, Just Be), Enya (Amarantine),
Kate Bush (Aerial), Klaus Schønning (Symphonies of Wellness), Kailash Kokopelli
(Prayer Flute), Shu-Bi-Dua, Børnekor, Jasmins musik, Midi collection, Lånt musik.

> MOD/S3M/XM/IT tracker music (3,332 files — nearly 30% of the music folder) is
> demoscene format, overwhelmingly collected during the BBS/FidoNet era
> (early–mid 1990s). The presence of this collection alongside the ZOC terminal and
> Borland C++ on gammel-disk makes the FidoNet period the earliest well-evidenced arc.
>
> The MIDI collection and classical/ambient music taste (Klaus Schønning, Kailash
> Kokopelli, Enya) alongside DJ Tiësto (trance) paints a specific texture.

---

## Documents: notable subdirectories

**Verified from monique (LIMA-2/Documents/, 826 files):**

| Subdir | Files | Signal |
|--------|-------|--------|
| `Flame/` | Multiple | Flame arts organisation — grant applications to Amager bydelspuljen (2010), member lists, bylaws |
| `Jasmin Skole/` | Present | School materials for Jasmin |
| `Essex-Park-III/` | Present | Apartment property — vedtaegter (bylaws/constitution) scans |
| `Stuff/GoldED src/` | ARJ files | GoldED FidoNet editor source archives (`.ARJ` format, GSRC0930.ARJ through GOLDED.ARJ) |
| `My Call Graphs/` | Present | Conference call MP3 recordings — IM business era (real example: `Echo Sound Test Service, 11 57 PM, Sunday, 07 February 2010.mp3`) |
| `Camtasia Studio/` | Present | Screencast production — likely IM tutorial videos |
| `My Flash Movies/` | Present | Flash (SWF) production — `wordpress-howto-v1` confirms IM tutorial era |

> The Flame arts organisation grant applications are dated 2010 (from actual filenames:
> `Ansøgningsskema Amager bydelspuljen 2010 - v3.doc`). This pins a distinct life arc
> alongside the IM work — community arts involvement in Copenhagen.
>
> GoldED ARJ archives in Documents/Stuff/ combined with gstools on gammel-disk and
> the FidoNet importer in Nornir form a triangle of FidoNet evidence.

---

## Desktop: Digterstemmer

`Desktop/Digterstemmer/` — 126 files, all numbered Danish spoken poetry MP3s.
Example filenames from real data:
- `07 Juninatten.mp3`
- `36 Det Er Det Der Er Pennen....mp3`
- `55 Sejlads.mp3`
- `85 Dags Dato Rives Ud Af Kalenderen..mp3`

> This is a curated personal collection — numbered tracks, Danish poets, spoken word.
> The track titles are poems. Placement on the Desktop (not buried in Music) suggests
> it was in active use. A distinct cultural signal: someone who keeps poetry on their
> desktop.

Import as `folder_scope = 'desktop'` with `collection_path = 'Digterstemmer/{basename}'`.

---

## Out of scope

| Folder | Reason |
|--------|--------|
| `Sophie_C/Documents and Settings/` | Sophie's Windows XP user profile |
| `Sophie_C/Programmer/` | Sophie's installed programs |
| `Sophie_C/gamle diske/sophies-disk-c/` | Sophie's old disk C (18,641 files) |
| `Sophie_C/gamle diske/sophies-disk-d/` | Sophie's old disk D (778 files) |
| `Thales Work PC/` | Work computer backup — employer context (11,398 files) |
| `Nintendo DS/` | Game ROM backups (2,422 files) — not biographical |
| `DVD rips/` | Media consumption archive (974 files, 478GB) |
| `Movies/` | Same (69 files, 37GB) |
| `Xxx/` | Private — excluded by policy |
| `Downloads/WordPress/` | 7,985 tooling files |
| `Downloads/Goddess Nudes/` | Private — excluded by policy (6,111 files) |
| `Downloads/PHP/`, `Downloads/CSS/`, `Downloads/JavaScript/` | Tooling downloads |
| `Downloads/kompozer*/`, `Downloads/HVR*/`, `Downloads/mystique-wordpress*/` | Installers/themes |
| `gvo backups/` | Covered in IM arc phase |
| `Business/` | Covered in IM arc phase |
| `sd kort/` | Memory card contents (1,417 files) — likely duplicate of Pictures |
| `Stuff/`, `Ryd op/` | Holding directories — low signal |

> Sophie's PC backup (Documents and Settings, Programmer) is not in scope.
> Sophie is Jasmin's mother and Odinn's ex-wife. Her personal files are not
> Odinn's biographical record. gammel-disk is the exception — it is Odinn's own
> old machine stored within the Sophie_C folder structure.

---

## Canonical table

`personal_files` — one row per indexed file, regardless of type.

```
id, source_file_id, volume_label, directory_full_path,
folder_scope           varchar(100)   -- 'gammel-disk', 'documents', 'music',
                                      --  'recordings', 'desktop', 'private',
                                      --  'downloads-named'
collection_path        varchar(500)   -- relative path within scope root
                                      --  e.g. 'Music/DJ Tiësto/Elements of Life'
basename, extension, normalized_file_type,
size_bytes, fs_created_at, fs_modified_at,
timestamps
UNIQUE (source_file_id)
```

No content extraction. No audio metadata. Files stay on external drives.

---

## Folder scope values

| `folder_scope` | Interpretation |
|---|---|
| `gammel-disk` | Early tech era — 1990s DOS/Windows machine; FidoNet workstation |
| `documents` | Life administration, educational, creative project records |
| `music` | Taste and cultural identity evidence |
| `recordings` | Audio capture — screencast or call evidence |
| `desktop` | Curated personal artifacts |
| `private` | Personal audio/video/documents |
| `downloads-named` | Named downloads with known context (Bruce, DR P3, HTC Desire) |

---

## Import rules

- Filter on the explicit `directory_full_path` prefix allowlist above
- Apply the Downloads subfolder allowlist — do not import all of Downloads
- `normalized_file_type` filter: all types allowed — unlike the media importer,
  this phase covers personal files of any type
- Exclude macOS resource forks (`basename LIKE '._%'`)
- Exclude Subversion internals (`extension = 'svn-base'` or path contains `/.svn/`)
- Paginate monique `files` by `id`, 500 per page

---

## TDD order

1. Migration — `personal_files` table
2. `ImportPersonalFilesAction` — scope routing, allowlist enforcement, idempotent upsert
3. `import:personal-files` command — `{--volume=}`, `{--dry-run}`
4. `BuildPersonalFilesSourcePageHandoffAction` + `handoff:personal-files-source-pages`

---

## Acceptance

- All in-scope folders produce rows; out-of-scope produce none
- `gammel-disk` files get `folder_scope = 'gammel-disk'`
- `folder_scope` correctly assigned for all other subtrees
- `collection_path` is relative to the scope root, not the volume root
- Tracker music files (MOD/S3M/XM/IT) imported under `music` scope
- Digterstemmer files imported under `desktop` scope
- Sophie's disks produce zero rows
- Resource forks and svn-base files excluded
- Rerun is idempotent

---

## Out of scope for this phase

- Audio metadata extraction (ID3, tags) — not available in monique
- File content reading — compilation-phase concern
- Identifying what is in gammel-disk/source/ — content is for Muninn
- Flame member list parsing — if needed, a dedicated Flame arc importer
- Deduplication across volumes

---

## Specifications used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/media-collection-source-navigation.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
