# Muninn Biography Pipeline

## Purpose

Produce evidence-bound biographical structure from imported source material.

## Responsibilities

- extract dated facts and observable events
- build timeline-supporting structures
- relate people, places, artifacts, and arcs
- maintain provenance throughout

## Inputs

- canonical imported rows
- media and document metadata records
- validated generator output constrained to biography rules

## Outputs

- `wiki/muninn/` pages
- optional supporting derived records in MySQL

## Storage and state

- canonical source truth stays in source tables
- Muninn may create derived tables for event candidates, timeline links, and evidence bundles
- generated pages live in `wiki/muninn/`

## Processing rules

- chronology is primary
- interpretation is constrained
- contradictions are surfaced, not smoothed away
- media records may attach to date ranges, events, people, and places

## Forbidden behavior

- no personality inference
- no unsupported narrative smoothing
- no claim without source-row traceability

## Interfaces

- event extraction contract
- timeline assembly contract
- [evidence bundle contract](evidence-bundle-contract.md)

## Validation and testing

- tests cover provenance on generated event statements
- tests cover chronology handling
- tests cover media-link attachment behavior

## Review checklist

- claims stay observable
- chronology comes first
- provenance is preserved

## Acceptance checks

- a reader can trace biographical claims back to imported evidence
