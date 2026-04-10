# MySQL Storage Contract

## Purpose

Define the MySQL layout for canonical imported truth, shared run tracking, and supporting derived layers.

## Responsibilities

- store canonical normalized source data
- store shared operational records
- preserve provenance and rerun safety

## Inputs

- importer writes
- generator run metadata
- Muninn and Huginn supporting derived records

## Outputs

- queryable canonical source tables
- shared run and artifact records
- provenance links for generated content

## Storage and state

Required shared tables:

- `intake_records`
- `runs`
- `run_events`
- `run_artifacts`
- `provenance_links`

Source-owned tables use stable prefixes:

- `chatgpt_*`
- `facebook_*`
- `gmail_*`
- `sms_*`
- `twitter_*`
- `fidonet_*`
- `media_collection_*`
- `instagram_*`
- `linkedin_*`

## Processing rules

- `runs` is the shared operational table for intake, import, Muninn, Huginn, wiki compilation, and bounded Heimdallr work
- every run records a subsystem, operation name, status, started-at timestamp, and idempotency key or equivalent uniqueness input
- child runs may point at a parent run when one operation fans out into source-specific or stage-specific work
- use stable source identifiers where possible
- keep derived tables visibly derived
- never overwrite canonical raw-normalized fields with cleaned prose
- store enough provenance to trace markdown claims back to source rows

## Forbidden behavior

- no generic universal content table pretending all sources are the same
- no markdown-as-canonical-data
- no run logs as the only state mechanism

## Interfaces

- importer upsert contract
- provenance recording contract
- run recording contract

### Run recording contract

Each run record must be able to answer:

- what subsystem owned the work
- what operation was attempted
- what input scope or identity made this run distinct
- whether the run is pending, running, succeeded, failed, cancelled, or partially completed
- which parent run, if any, spawned it
- which artifacts and provenance links came from it

## Validation and testing

- migration tests cover core constraints
- tests cover uniqueness and rerun safety
- tests cover provenance link integrity

## Review checklist

- canonical vs derived is obvious
- prefixes are stable
- required shared tables are enough but not excessive

## Acceptance checks

- every imported fact has a durable relational home
