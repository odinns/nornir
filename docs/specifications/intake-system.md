# Intake System

## Purpose

Receive bounded source material or source descriptors and turn them into tracked intake records.

## Responsibilities

- accept uploads, paths, archive descriptors, and approved external references
- create intake records
- capture source metadata
- hand off work to the correct importer

## Inputs

- uploaded archives
- configured external paths
- approved API sync descriptors
- Heimdallr-fetched bounded artifacts

## Outputs

- intake records
- intake-to-import handoff payloads
- reviewable intake manifests under `data/intake/`

## Storage and state

- MySQL stores canonical intake records
- `data/intake/` stores manifests and diagnostics
- intake never becomes canonical source truth for imported content itself
- API-backed intake records store the source descriptor and scope snapshot that defined the fetch, not just a vague pointer to "Gmail" or similar

## Processing rules

- every intake gets a source type and scope
- every intake records where the source actually lives
- API-backed intake records must capture the access mode, account or connection identity, scope expression, and the timestamp or cursor boundary used for the fetch
- intake must persist enough source-descriptor detail to replay or audit an incremental sync without guessing
- external collections may be referenced without copying files
- intake decides which importer owns the next step

## Forbidden behavior

- no normalization into source tables here
- no Muninn or Huginn inference here
- no silent copying of giant external corpora

## Interfaces

- queueable intake action
- importer dispatch contract
- intake review manifest output

### Importer dispatch contract

The intake-to-import handoff payload must include:

- intake record id
- source type
- access mode such as local-path, archive, api-scope, or heimdallr-fetch
- concrete source locator or approved descriptor
- scope snapshot
- importer-local options

For API-backed sources, the scope snapshot must include the replay unit for incremental work, such as a query string plus fetched-at boundary or a provider history cursor.

### Intake review manifest output

The review manifest must make it obvious:

- what was requested
- what source boundary was actually used
- what importer will own the next step
- what replay or incremental marker was recorded

## Validation and testing

- validates source reachability
- validates allowed source type
- tests cover accepted inputs, rejected inputs, and correct importer dispatch

## Review checklist

- intake remains shallow
- source scope is explicit
- no downstream logic leaks in

## Acceptance checks

- a new source can enter the system without bypassing intake tracking
