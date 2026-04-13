# Phase 1: Internet Marketer Arc Importer

## Summary

Index the internet marketing business records stored in `monique` as biographical
evidence for the IM life arc (roughly 2006–2012). The source is `Business/` on LIMA-2,
not a photo archive. The biographical signal is in project names, collaborator names,
email marketing sequences, and sales copy — not in binaries or code scaffolding.

This is a sibling to the media collection importer and reads from the same `monique` DB.

---

## What is actually there

### Business/Projects (~79k files, 30+ named projects)

Website and giveaway project source trees backed by Subversion. Each top-level
subdirectory is a named business venture. **Verified from monique (2026-04-13):**

| Project | Files | Notes |
|---------|-------|-------|
| Daddys Birthday | 28,374 | Largest project by far; giveaway site for 40th birthday milestone; multiple Subversion snapshot dates visible in tree |
| Flame | 6,148 | |
| Subversion | 5,148 | Subversion tool itself — not a project, skip |
| Internet Marketing Money Maker | 4,642 | IM training product (IM3) |
| Odinn Sorensen | 4,200 | Personal brand / authority site |
| Odinns | 3,835 | |
| Unfuddle backup | 3,450 | Unfuddle was a hosted SVN service — backup of repo, not a product |
| Zero Effort Sales | 3,259 | |
| Zero Effort Graphics | 2,206 | |
| Rhonda Cort | 2,130 | Collaborator — joint venture or her own site managed by Odinn |
| Demo Giveaway | 1,942 | |
| Reset The Mind | 1,883 | |
| TantaZone DK | 1,555 | Danish-market product |
| Product Creation Giveaway | 1,257 | |
| Stacy Makdad | 737 | Collaborator |
| The Great Viking Giveaway | 684 | |
| Impact Action Giveaway | 653 | |
| Simple Twitter Giveaways | 648 | |
| School Days Giveaway | 562 | |
| Impact Book Launch | 532 | |
| Bruce C. Fein | 502 | Collaborator |
| Jaime Luchuck | 502 | Collaborator |
| Great Cookbook Giveaway | 461 | |
| Dollar Deal Deluxe | 445 | |
| Graphics Giveaway | 370 | |
| Giveaway Engine | 333 | Software product |
| IM3 System v2 | 292 | Second version of Internet Marketing Money Maker |
| Launch Database | 202 | |

> Collaborator names appearing as project directory names (Rhonda Cort, Stacy Makdad,
> Jaime Luchuck, Bruce C. Fein) suggest Odinn was building or managing sites for/with
> these people — not just his own products.

**File type breakdown for Business/Projects (from monique):**
PHP (24,306), JPG (15,856), SVN-base internals (10,241), HTML (6,473), GIF (6,182),
TXT (2,092), PNG (1,871), JPEG (1,357), TPL templates (1,271), JS (1,224), CSS (952).

The code scaffolding (PHP, JS, CSS, SVN internals) is not biographical. The useful
content is:
- Sales page HTML (`index.html`, `index.php`, `salespage.*`, `download.*`)
- PDF product documents (206 total)
- SQL schema snapshots (209 total — imply product structure and date of snapshot)

### Business/aweber_lists_odinn_2012_01_30 (~2,158 files)

AWeber email marketing platform export, snapshotted 2012-01-30. Contains:
- **Broadcasts** — dated one-off emails; filename encodes `YYYY_MM_DD_HHMM_subject`
- **Followups** — autoresponder sequence emails; filename encodes position and subject

**Lists confirmed present (from directory listing):**
- `dbg40-partners` — Daddy's Birthday Giveaway 40 Years partners list
- `odinns-agv-gift` — Amazing Graphics Videos
- `odinns-plrvault` — PLR Vault product list (broadcasts date back to 2006-11-11 —
  the earliest dated evidence in the entire IM arc)
- `im3-buyers` — Internet Marketing Money Maker buyers

Each email exists as both `.htm` and `.txt` variants. Import `.txt` only.

**Real broadcast filename examples:**
- `2006_11_11_0817_test_1.txt` — earliest dated broadcast, Nov 2006
- `2007_10_07_0933___firstname_fix___Here_Is_Your__70__Gig_Webspace_.txt`
- `1___firstname_fix___welcome_to_the_Internet_Marketing_Money_Maker_buyers_list_.txt`

Broadcast dates span 2006–2012 — this is the most precisely dated biographical text
evidence in the entire Business tree. The PLR Vault list starting in November 2006
suggests that as the IM arc start date.

### Business/gvo backups (~12,657 files)

Website backups from GVO (Global Virtual Opportunities), a hosting provider popular
in the IM community circa 2006–2012. File type breakdown: PHP (2,848), SVN internals
(2,528), JPG (1,747), HTML (956), PNG (828), GIF (730), JS (609), CSS (401),
ZIP (309). Deferred — secondary, lower signal than Projects.

### Business/Resale, Purchases, Artwork, Software (~37k files combined)

Purchased graphics packs, resale rights products, stock assets, installers.
Not biographical. Do not import.

---

## Canonical tables

### `im_projects`

One row per named business project (top-level directory under `Business/Projects/`,
excluding `Subversion/` and `Unfuddle backup/` which are tooling not products).

```
id, source_directory_id, project_name, project_key (slug), volume_label,
directory_full_path, file_count, earliest_file_date, latest_file_date,
timestamps
UNIQUE (source_directory_id)
```

### `im_aweber_broadcasts`

One row per broadcast email file (`.txt` variant only; `.htm` is redundant).

```
id, source_file_id, list_name, broadcast_at (datetime parsed from filename),
subject (parsed from filename), content_path (full_path for reading if needed),
size_bytes, timestamps
UNIQUE (source_file_id)
```

### `im_aweber_followups`

One row per followup sequence email.

```
id, source_file_id, list_name, sequence_position (int), subject (parsed from filename),
content_path, size_bytes, timestamps
UNIQUE (source_file_id)
```

---

## Filename parsing

### Broadcast filename → date + subject

Pattern: `YYYY_MM_DD_HHMM_subject_words_with_underscores.txt`

Real example: `2007_10_07_0933___firstname_fix___Here_Is_Your__70__Gig_Webspace_.txt`

- Date: `2007-10-07 09:33`
- Subject: strip leading/trailing underscores, collapse `__` to space, strip
  AWeber merge tags (`___firstname_fix___`, `___lastname_fix___`, etc.)
- Result: `Here Is Your 70 Gig Webspace`

### Followup filename → position + subject

Pattern: `{N}___firstname_fix___subject_words.txt`

Real example: `1___firstname_fix___welcome_as_partner_on_Daddys_Birthday_Giveaway_40_Years_.txt`

- Position: leading integer (`1`)
- Subject: `Welcome As Partner On Daddys Birthday Giveaway 40 Years`

---

## What to skip

| Path pattern | Reason |
|---|---|
| `Business/Resale/` | Stock assets, not biographical |
| `Business/Purchases/` | Purchased products, not biographical |
| `Business/Artwork/` | Graphics packs — 7,193 files of clip art and minisite graphics |
| `Business/Software/` | Installers — 2,242 files |
| `Business/gvo backups/` | Defer — secondary, lower signal |
| `Business/Projects/Subversion/` | The SVN tool itself, not a project (5,148 files) |
| `Business/Projects/Unfuddle backup/` | SVN hosting backup, not a product (3,450 files) |
| `.svn/` paths, `svn-base` extension | Subversion internals — 10,241 files in Projects alone |
| `.php`, `.js`, `.css` in Projects | Code scaffolding |
| `.htm` AWeber variant | Redundant — `.txt` is the canonical copy |
| `followup_summary.csv`, `broadcast_summary.csv` | AWeber index files, redundant |

---

## Source access

Same `monique` DB bridge as the media collection importer. Read-only.
Paginate `files` ordered by `id`. Filter by `directory.full_path` prefix and extension.

---

## TDD order

1. Migration — three tables with correct columns and unique constraints
2. `ImportInternetMarketerArcAction` — project indexing, broadcast parsing, followup parsing
3. `import:im-arc` command — output strings, DB state
4. `BuildImArcSourcePageHandoffAction` + `handoff:im-arc-source-pages` command

---

## Acceptance

- All 28+ named projects appear in `im_projects`, excluding Subversion and Unfuddle backup
- Daddys Birthday row shows ~28k files
- Broadcasts parsed with correct dates (spot-check: `odinns-plrvault` earliest = 2006-11-11)
- Followup sequence positions parsed correctly
- `.htm` variants not imported
- Skipped subtrees (Resale, Purchases, Artwork) produce zero rows
- Rerun is idempotent

---

## Out of scope for Phase 1

- Reading the actual HTML/text content of sales pages or emails into the database
  (compilation-phase concern — Muninn reads files at compile time via `content_path`)
- Subversion history extraction
- GVO backup indexing
- Collaborator entity resolution (Rhonda Cort, Stacy Makdad, etc. noted in project
  names but not linked to people records — that is a Muninn synthesis concern)

---

## Specifications used

- `docs/specifications/intake-system.md`
- `docs/specifications/importer-framework.md`
- `docs/specifications/mysql-storage-contract.md`
- `docs/specifications/orchestration-runs-jobs-and-provenance.md`
- `docs/specifications/media-collection-source-navigation.md` (same DB bridge pattern)
