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
- shared run state lives in the `runs` table, not a source-specific table name
- `data/runs/` stores review-friendly mirrors and summaries

## Processing rules

- every substantial operation gets a run record
- run identity is scoped by subsystem, operation, and the input identity or scope being processed
- a run may have a parent run when orchestration fans out into child work
- rerunning the same operation for the same input must either reuse or supersede the prior logical run intentionally; it must not create ambiguity
- run events are append-only
- artifacts are discoverable by run
- run artifacts under `data/runs/` are mirrors for review, not the only operational record
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

### Run recorder contract

The run recorder must capture at minimum:

- subsystem
- operation name
- status
- input scope or identity snapshot
- idempotency key or equivalent uniqueness input
- parent run id when present
- start and finish timestamps
- failure summary when the run does not succeed cleanly

### Artifact recorder contract

Artifacts must record:

- owning run id
- artifact kind
- stable path or external reference
- whether the artifact is canonical, derived, compiled, or diagnostic

### Provenance writer contract

Every persisted claim-bearing output must record:

- owning run id
- output target
- claim or section identifier
- one or more supporting source-row or evidence-bundle references

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
