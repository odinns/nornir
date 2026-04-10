# Heimdallr Access Boundary

## Purpose

Provide bounded read access to external systems, archives, and tools without letting the rest of the app wander off into the woods.

## Responsibilities

- define allow-listed external access
- fetch bounded content when approved
- hand fetched material into intake
- host MCP-facing access logic in v1

## Inputs

- explicit user or system-approved access requests
- configured credentials or access descriptors

## Outputs

- bounded fetched artifacts
- intake-ready references
- access logs and diagnostics

## Storage and state

- fetched artifacts are transient until intake and import take ownership
- access logs may be written to MySQL and `data/runs/`

## Processing rules

- read-only in v1
- explicit allow rules
- bounded traversal only
- no hidden background crawling

## Forbidden behavior

- no write-back to external systems
- no uncontrolled filesystem or API traversal
- no direct bypass around intake and import

## Interfaces

- access request contract
- bounded fetch contract
- MCP integration contract

## Validation and testing

- tests cover denied access
- tests cover bounded traversal enforcement
- architecture tests ensure external access stays behind Heimdallr

## Review checklist

- scope is explicit
- read-only is preserved
- fetched content enters through the right boundary

## Acceptance checks

- external access stays auditable and boring
