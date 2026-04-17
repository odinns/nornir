# Apple Health Source Navigation

## Start here

Primary input is an Apple Health export directory from iPhone, using `eksport.xml` as the canonical importer input.

## Canonical source

- Apple Health export XML in `eksport.xml`
- `export_cda.xml` only as secondary reference material

## Source layout or access model

- export directory or direct XML file path
- `Record` elements
- `Workout` elements
- profile metadata in `Me`
- export metadata in `ExportDate`

## Important entities

- source sets
- records
- workouts

## Traversal rules

- import all `Record` and `Workout` entries
- skip `Me` and `ExportDate` as canonical medical facts in v1
- preserve Apple Health type names and source metadata

## Safe access rules

- treat the export as immutable
- resolve handoff from canonical rows, not by rescanning raw XML

## Parser notes

- timestamps arrive with offsets and must be normalized to UTC instants
- Apple Health source app names and versions are part of record identity
- `eksport.xml` may be large, so the importer should stream rather than load everything eagerly

## Bottom line

Apple Health is XML-first, `eksport.xml`-first, and importer-owned canonicalization should stay generic rather than inventing a fake health ontology.
