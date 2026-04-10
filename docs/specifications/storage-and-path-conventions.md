# Storage And Path Conventions

## Purpose

Define where things live and, more importantly, where they do not.

## Repository policy

Tracked in git:

- application code
- tests
- migrations
- configuration
- docs
- prompt and skill assets

Not tracked in git:

- imported raw material
- generated markdown
- run artifacts
- exported payloads
- database dumps
- caches
- temporary files

## Top-level directories

Application-owned:

- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `docs/`
- `resources/`
- `routes/`
- `tests/`

Non-versioned local roots:

- `data/`
- `wiki/`

## `data/` layout

Use logical names only:

- `data/sources/`
- `data/intake/`
- `data/imports/`
- `data/runs/`
- `data/exports/`
- `data/reviews/`
- `data/tmp/`

Meaning:

- `data/sources/`: odd file-based source material that is not a large structured archive, for example CVs, personality tests, or a site dump snapshot
- `data/intake/`: intake manifests and diagnostics
- `data/imports/`: importer-side exports and review files
- `data/runs/`: run logs and summaries
- `data/exports/`: explicit outbound exports
- `data/reviews/`: human-review bundles
- `data/tmp/`: disposable working files

## `wiki/` layout

Generated markdown only:

- `wiki/sources/`
- `wiki/muninn/`
- `wiki/huginn/`
- `wiki/outputs/`

Optional compiled helpers:

- `wiki/index.md`
- `wiki/log.md`

## Source material policy

Raw source material remains outside version control.

Permitted realities:

- directly imported from bounded external locations
- stored in local ignored `data/sources/`
- referenced via Heimdallr or explicit source configuration

Working rule:

- use `data/sources/` for local non-versioned source material, whether it is a single odd file or a larger local corpus
- keep archive-like material in clearly named subfolders under `data/sources/`
- keep all of it non-versioned

## External collections

Large photo, video, PDF, and document collections remain outside Nornir-managed storage.

Nornir stores:

- references
- folder structure
- metadata
- provenance

Nornir does not store:

- copied binaries
- silent mirrors of external folders

## Review checklist

- every path has one purpose
- operational outputs are ignored by git
- logical names are used instead of subsystem mythology

## Acceptance checks

- a new feature can choose a storage location without guessing
- no generated state needs to be committed to function
