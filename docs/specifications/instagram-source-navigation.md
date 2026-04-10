# Instagram Source Navigation

## Start here

This is a planned optional source, expected to use export archives first and official API access where appropriate and permitted.

## Canonical source

- downloadable archive when available
- official API responses when stable and allowed

## Source layout or access model

- profile metadata
- posts
- stories or reels metadata where available
- media references
- interactions if access permits

## Important entities

- profile snapshots
- posts
- captions
- timestamps
- media references

## Traversal rules

- prefer bounded archive import
- API use must be explicitly scoped and supported

## Safe access rules

- no scraping in the base design
- low-volume data is still valid input

## Parser notes

- archive and API availability may change
- design the importer to degrade gracefully when the source surface is sparse

## Bottom line

Instagram is planned, optional, and bounded by supported access modes.
