# Testing And TDD Strategy

## Purpose

Define how Nornir gets built without relying on vibes and last-minute bravery.

## Default test stack

- Pest is the default framework
- Pest architecture tests are required from the beginning

## TDD stance

For new behavior:

1. write the failing test
2. implement the smallest working change
3. refactor while the tests stay green

## Test layers

### Feature tests

Use for:

- importer commands
- intake workflows
- queue job orchestration
- HTTP endpoints when Mimir arrives
- end-to-end generator runs with faked model responses

### Unit tests

Use for:

- parsers
- normalizers
- provenance builders
- date extraction helpers
- metadata extractors
- prompt-output validators

### Architecture tests

Enforce:

- allowed namespace dependencies
- forbidden subsystem reach-through
- Laravel layer boundaries
- prompt assets staying out of controllers
- Mimir not depending on raw importer internals

## Importer test requirements

Every importer needs tests for:

- happy path import
- rerun idempotency
- malformed input handling
- validation failure surfacing
- provenance preservation

## Generator test requirements

Every AI generator needs tests for:

- prompt or skill resolution
- structured output validation
- provenance enforcement
- refusal on missing evidence scope
- persistence to the correct markdown target

## Media collection importer tests

Required:

- folder traversal within the allowed root only
- metadata extraction when present
- graceful handling when EXIF or document metadata is absent
- stale external reference detection

## Review checklist

- tests hit behavior, not just class existence
- architecture tests encode the important boundaries
- regression tests exist for rerun semantics

## Acceptance checks

- subsystem boundaries can fail the suite when broken
- importers and generators are safe to refactor under tests
