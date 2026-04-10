# Media Collection Source Navigation

## Start here

This source is a bounded external folder tree containing photos, videos, PDFs, and similar files.

## Canonical source

- external directory tree
- filesystem metadata
- embedded metadata such as EXIF where available

## Source layout or access model

- collection root
- nested folders
- files
- sidecar metadata files when present

## Important entities

- collection roots
- folders
- files
- embedded metadata blocks

## Traversal rules

- stay within the configured root
- record relative paths and folder hierarchy
- extract metadata without copying binaries

## Safe access rules

- no traversal outside the bounded root
- no binary import into Nornir-managed storage

## Parser notes

- metadata availability varies widely
- missing EXIF is normal, not an error by itself

## Bottom line

This importer indexes metadata and references only; the files remain outside Nornir.
