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
- page identity must be stable enough that reruns overwrite or version intentionally instead of spraying near-duplicate files

## Forbidden behavior

- no editing canonical source truth by editing markdown
- no unsupported claims
- no mixed Muninn and Huginn rules on the same page without making the boundary explicit

## Interfaces

- page type contract
- filename and slug contract
- provenance embedding contract

### Page type contract

Each page must declare one of:

- source
- muninn
- huginn
- output

That page type controls:

- which `wiki/` subtree owns the file
- which generation rules apply
- whether interpretation is constrained or allowed

### Filename and slug contract

- slugs must be deterministic from page type plus stable source or subject identity
- rerunning the same logical page must resolve to the same target path unless the generator is intentionally creating a versioned output page
- filenames must avoid leaking temporary run ids into stable page identities
- if a page is intentionally versioned, the version token must be explicit and derived from a durable policy, not an incidental timestamp

### Provenance embedding contract

- every generated page must point to its owning run id
- every claim-bearing section must be traceable either through embedded metadata or through stable section identifiers linked in MySQL
- provenance references must resolve to source rows or evidence bundles, not just free-text citations
- missing provenance for any non-trivial section is a generation failure, not a warning

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
