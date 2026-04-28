# Huginn Personality Pipeline

## Purpose

Produce evidence-backed interpretive outputs about patterns, preferences, behaviors, and recurring themes.

## Responsibilities

- synthesize across sources
- record observations and working models
- preserve support traces for every non-trivial claim
- consume judgment observations before writing broader self-models

## Inputs

- canonical imported rows
- Muninn evidence bundles where useful
- judgment observation records
- validated Huginn generator output

## Outputs

- `wiki/huginn/` pages
- supported observation and synthesis records
- optional supporting derived records in MySQL

## Storage and state

- Huginn does not own canonical truth
- derived observation and support-link records may live in MySQL
- generated pages live in `wiki/huginn/`
- decision and correction policy lives in `judgment-layer-contracts.md`

## Processing rules

- synthesis is allowed
- evidence is mandatory
- contradictions stay visible
- support can come from multiple sources, but each supporting row must be identifiable
- raw source rows should become observations before they become broad personality claims

## Forbidden behavior

- no unsupported personality claims
- no vague trait sludge
- no rewriting source material into fake certainty
- no bypassing judgment observations for high-level self-model claims

## Interfaces

- observation contract
- support-trace contract
- generator validation contract
- judgment-layer contract

## Validation and testing

- tests cover refusal on weak evidence
- tests cover support-trace persistence
- tests cover page generation into the correct location
- tests cover synthesis from observation records instead of unsupported raw-source jumps

## Review checklist

- synthesis adds signal instead of perfume
- claims are evidenced
- Muninn boundaries remain intact

## Acceptance checks

- Huginn pages can be challenged and audited against actual source evidence
