# Orchestration, Runs, Jobs, And Provenance

## Purpose

Define how long-running work is scheduled, recorded, and traced back to evidence.

## Responsibilities

- orchestrate intake, import, Muninn, and Huginn jobs
- record runs consistently
- persist provenance for generated outputs

## Inputs

- queued commands and actions
- subsystem handoff payloads

## Outputs

- run rows
- run event rows
- artifact rows
- provenance links
- reviewable run files under `data/runs/`

## Storage and state

- MySQL is the authoritative operational record
- `data/runs/` stores review-friendly mirrors and summaries

## Processing rules

- every substantial operation gets a run record
- run events are append-only
- artifacts are discoverable by run
- generators record prompt version and model metadata
- provenance links connect markdown claims or sections to source rows or evidence bundles

## Forbidden behavior

- no untracked batch jobs
- no generator output without run metadata
- no provenance gaps hidden by successful status

## Interfaces

- run recorder contract
- artifact recorder contract
- provenance writer contract

## Validation and testing

- tests cover run creation and completion
- tests cover partial and failure status handling
- tests cover provenance-link completeness

## Review checklist

- important work is auditable
- failures are visible
- provenance survives the pipeline

## Acceptance checks

- any generated page can be traced to a run and to supporting evidence
