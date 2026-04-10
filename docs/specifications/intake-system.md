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

## Processing rules

- every intake gets a source type and scope
- every intake records where the source actually lives
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
