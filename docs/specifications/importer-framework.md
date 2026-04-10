# Importer Framework

## Purpose

Define how source-specific importers plug into Nornir without collapsing into one mega-command.

## Responsibilities

- provide one importer per source
- share only plumbing
- standardize run recording, validation, and provenance hooks

## Inputs

- intake handoff payloads
- source-local configuration
- optional dry-run and validation mode flags

## Outputs

- canonical normalized rows in MySQL
- run records
- artifacts under `data/imports/` and `data/runs/`
- wiki compilation handoff payloads

## Storage and state

- shared infrastructure tables for runs and artifacts
- source-prefixed tables for canonical normalized content
- optional derived importer tables where justified

## Processing rules

- one command or action per source
- shared flags mean the same thing across sources
- source-specific flags stay local
- reruns must be idempotent
- logical identity and source occurrence identity must be distinguished where needed

## Forbidden behavior

- no fake universal source model
- no importer writes directly into Huginn or Muninn markdown
- no source-specific branching monolith

## Interfaces

- source-scoped import command
- shared run recorder
- validation result contract
- wiki handoff contract

## Validation and testing

- every importer gets feature and unit tests
- rerun behavior is mandatory to test
- malformed inputs must surface clearly

## Review checklist

- shared code is actually shared plumbing
- source logic lives with the source
- import and compilation remain separate

## Acceptance checks

- adding a new importer does not require editing a giant switchboard
