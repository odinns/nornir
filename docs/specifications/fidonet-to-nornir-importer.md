# FidoNet To Nornir Importer

## Goal

Integrate canonical FidoNet message data into Nornir with derived cleanup and threading support.

## Canonical source

Existing GoldED/FidoNet database. This companion source is not public yet.

## Inputs

- connection configuration
- area scope
- optional exclusion filters

## Output structure

- canonical linkage records or synchronized projections as justified
- derived `fidonet_*` helper tables
- run artifacts under `data/imports/fidonet/`

## MySQL storage model

- preserve the external canonical boundary
- use `fidonet_*` tables for Nornir-owned derived or integration state

## Data model

Track canonical message ids, area identity, cleanup views, and thread projections.

## Import rules

- do not duplicate canonical truth without reason
- derive cleanup and threads visibly

## Incremental behavior

- rerun by canonical ids and source scope

## Validation

- source connectivity
- canonical-id preservation
- derived thread integrity

## Source handoff

- source and evidence pages build from canonical references plus derived views

## Forbidden behavior

- no fake full-copy import just for symmetry

## Review checklist

- canonical DB boundary is preserved
- derived tables are honestly named

## Acceptance checks

- FidoNet can feed Muninn and Huginn without pretending its canonical database does not exist
