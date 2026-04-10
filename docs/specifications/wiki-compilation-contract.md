# Wiki Compilation Contract

## Purpose

Define how canonical and derived data becomes generated markdown in `wiki/`.

## Responsibilities

- compile durable markdown pages
- keep page types consistent
- preserve provenance into page metadata or adjacent records as needed

## Inputs

- canonical source rows from MySQL
- derived evidence records
- validated AI generator output

## Outputs

- `wiki/sources/*`
- `wiki/muninn/*`
- `wiki/huginn/*`
- `wiki/outputs/*`
- optional `wiki/index.md` and `wiki/log.md`

## Storage and state

- markdown files are compiled artifacts
- write metadata about generation runs to MySQL and `data/runs/`
- do not treat the markdown files as authoritative source state

## Processing rules

- source pages summarize one source or source instance
- Muninn pages stay evidence-first and chronology-first
- Huginn pages may synthesize but must cite support
- output pages are saved analyses worth keeping

## Forbidden behavior

- no editing canonical source truth by editing markdown
- no unsupported claims
- no mixed Muninn and Huginn rules on the same page without making the boundary explicit

## Interfaces

- page type contract
- filename and slug contract
- provenance embedding contract

## Validation and testing

- tests cover page placement and naming
- tests cover generator rejection on missing provenance
- tests cover no-write outside `wiki/`

## Review checklist

- compiled output stays visibly compiled
- page type is clear
- provenance survives compilation

## Acceptance checks

- markdown remains durable output without becoming hidden primary state
