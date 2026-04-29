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

- review artifacts under `data/reviews/`
- `wiki/muninn/` pages
- optional supporting derived records in MySQL

## Storage and state

- canonical source truth stays in source tables
- first-pass biography candidates may be JSON review artifacts before any durable Muninn table exists
- Muninn may create derived tables for event candidates, timeline links, and evidence bundles
- generated pages live in `wiki/muninn/`

## Current review artifact slice

`muninn:gmail-biography-candidates` reads a validated `gmail-important-mail` evidence bundle and writes `data/reviews/muninn-biography-candidates-run-{evidence_run_id}.json`.

This artifact is deliberately conservative:

- no AI generation
- no Markdown parsing
- no `wiki/muninn/` output
- no durable derived candidate table
- one provenance link per chronology candidate

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
