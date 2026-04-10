# Nornir Spec Conventions

## Purpose

Keep the detailed specs structurally compatible, operationally honest, and boring in the good way.

## Core stance

- every source has one canonical truth boundary
- MySQL is the canonical working store for imported source material unless a source already has a justified canonical database
- `wiki/` is compiled output
- `data/sources/` is the local non-versioned home for source material
- `data/` is local non-versioned operational state
- prompts, runs, exports, and projections are derived
- Mimir is downstream of stable backend contracts

## Required document types

Most sources get two docs:

1. source navigation
2. importer spec

Subsystems get one detailed spec each.

## Naming rules

Use these filenames:

- `<source>-source-navigation.md`
- `<source>-to-nornir-importer.md`

Subsystem filenames should be literal, not clever.

## Required spine for subsystem specs

Use this order:

1. Purpose
2. Responsibilities
3. Inputs
4. Outputs
5. Storage and state
6. Processing rules
7. Forbidden behavior
8. Interfaces
9. Validation and testing
10. Review checklist
11. Acceptance checks

## Required spine for source navigation specs

Use this order:

1. Start here
2. Canonical source
3. Source layout or access model
4. Important entities
5. Traversal rules
6. Safe access rules
7. Parser notes
8. Bottom line

## Required spine for importer specs

Use this order:

1. Goal
2. Canonical source
3. Inputs
4. Output structure
5. MySQL storage model
6. Data model
7. Import rules
8. Incremental behavior
9. Validation
10. Wiki compilation handoff
11. Forbidden behavior
12. Review checklist
13. Acceptance checks

## Language rules

- say canonical when it is actually the source of truth
- say derived when it is computed, cleaned, enriched, or compiled
- do not call markdown canonical
- do not call run logs the data model
- if one approach is clearly better, say so

## Cross-spec invariants

- importers do not write narrative truth
- Muninn does not do personality inference
- Huginn may synthesize only with evidence
- Heimdallr stays read-only
- Mimir does not own core logic

## Review checklist

- truth boundaries are explicit
- rerun behavior is explicit
- provenance is explicit
- storage location is explicit
- forbidden behavior is explicit

## Acceptance checks

- another engineer can implement from the spec without inventing policy
- the spec does not blur canonical, derived, and compiled layers
