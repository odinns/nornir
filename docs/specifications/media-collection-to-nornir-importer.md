# Media Collection To Nornir Importer

## Goal

Index external media and document collections into MySQL so they can support biographical timeline evidence without copying the files.

## Canonical source

Bounded external collection roots plus filesystem and embedded metadata.

## Inputs

- root path
- collection label
- optional include or exclude patterns
- dry-run or validate-only flags

## Output structure

- canonical `media_collection_*` tables
- run artifacts under `data/imports/media-collection/`

## MySQL storage model

- `media_collection_roots`
- `media_collection_files`
- `media_collection_metadata`
- optional `media_collection_sidecars`

## Data model

Store:

- root label
- relative path
- folder path
- filename
- extension and media type
- file size
- observed timestamps
- extracted EXIF or document metadata when available
- optional checksum where justified

## Import rules

- store references and metadata only
- binaries remain outside Nornir-managed storage
- sidecar metadata may be indexed when present

## Incremental behavior

- rerun by collection root and relative path identity, updating metadata idempotently

## Validation

- bounded traversal
- unreadable file reporting
- stale external reference detection
- metadata extraction reporting

## Wiki compilation handoff

- media records may be attached to Muninn evidence, events, places, and date ranges

## Forbidden behavior

- no binary copying
- no thumbnail-first architecture
- no claiming media dates are definitive without source-specific confidence rules

## Review checklist

- external ownership is preserved
- metadata is separated from binaries
- chronology use is careful

## Acceptance checks

- media collections can enrich timelines without becoming repo baggage
