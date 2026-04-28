# Judgment Layer Contracts

## Purpose

Define how Nornir captures evidence-backed judgment without turning generated markdown or model output into truth.

This layer covers decisions, observations, contradictions, corrections, and promoted analyses. It is not a new source system. It is derived state built from canonical source rows, evidence bundles, and explicit user corrections.

## Responsibilities

- extract decision candidates from bounded evidence
- preserve recurring observations before Huginn turns them into synthesis
- surface contradictions as review targets
- record user corrections as explicit inputs for derived claims
- promote useful Q&A or analysis runs into durable outputs
- keep every claim traceable to support

## Inputs

- canonical source rows from MySQL
- source handoff payloads
- Muninn evidence bundles
- validated generator output
- user corrections
- promoted analysis or Q&A runs

## Outputs

- decision candidate records
- accepted decision records
- judgment observation records
- contradiction records
- correction records
- promoted output records
- generated markdown under `wiki/huginn/*` and `wiki/outputs/*`

## Storage and state

- MySQL owns the judgment records
- all judgment records are derived except user correction records
- user corrections are canonical as corrections, not as edits to the original source material
- generated markdown is compiled output only
- run metadata and provenance links are mandatory

Suggested table families:

- `decision_candidates`
- `decisions`
- `decision_supports`
- `judgment_observations`
- `observation_supports`
- `contradiction_records`
- `user_corrections`
- `promoted_outputs`

These names are intentionally plain. Rename only if the implementation has a better reason than taste.

## Processing rules

- decisions start as candidates unless the source evidence is explicit enough to accept directly
- explicit decision evidence means the source names the choice, context, and reason clearly enough to preserve without inference
- accepting a decision requires support links and either strong evidence or deliberate user confirmation
- observations must stay below synthesis until enough support exists
- Huginn may synthesize from observation records, but the support trace remains the product
- contradictions record both sides and their support; they are not resolved by smoothing prose
- corrections target derived claims, output sections, records, or support links
- corrections may suppress or supersede derived claims, but must not rewrite imported source rows
- promoted outputs need a stable slug, owning run id, original question or prompt, and support links
- reruns must supersede or refresh derived records intentionally instead of creating duplicate near-truths

## Forbidden behavior

- no manual wiki edits as the correction mechanism
- no accepted decisions without support
- no personality synthesis directly from raw source rows when an observation ledger is missing
- no contradiction hidden because one side is more convenient
- no correction that mutates canonical imported source material
- no automatic promotion of every answer into durable output
- no generic trait sludge

## Interfaces

- decision contract
- observation contract
- contradiction contract
- correction contract
- promotion contract

### Decision contract

A decision candidate must record:

- subject
- decision statement
- decision status: candidate, accepted, rejected, superseded
- context summary
- one or more support references
- alternatives when present in the evidence
- consequences when present in the evidence
- owning run id

An accepted decision must also record:

- accepted at
- acceptance reason
- whether acceptance came from evidence strength or user confirmation

### Observation contract

An observation must record:

- observed behavior or pattern
- interpretation level: low, medium, high
- first seen and last seen when knowable
- support references
- contradiction references when present
- owning run id

Low interpretation means the record stays close to the evidence. High interpretation is allowed only after enough support exists to make the claim challengeable instead of decorative.

### Contradiction contract

A contradiction must record:

- contradiction type
- left claim or record
- right claim or record
- support references for each side
- review status
- resolution note when resolved

### Correction contract

A correction must record:

- correction text
- corrected target type
- corrected target id or stable section id
- reason
- author
- accepted at
- resulting action: suppress, supersede, amend, or annotate

### Promotion contract

A promoted output must record:

- original question or prompt
- answer or analysis run id
- stable slug
- output type
- support references
- promotion reason
- generated markdown target when compiled

## Validation and testing

- tests reject accepted decisions without support
- tests reject observations without support
- tests prove Huginn synthesis can be traced back through observation support
- tests keep contradictions visible until explicitly resolved
- tests prove corrections do not mutate canonical source rows
- tests reject promoted outputs without stable identity and provenance

## Review checklist

- authority stays in MySQL and source evidence
- derived records are visibly derived
- corrections are explicit inputs, not secret edits
- decisions explain why they were accepted
- contradictions are useful, not buried
- markdown remains compiled output

## Acceptance checks

- Nornir can answer "what did I decide, why, and what supported it?"
- Nornir can show recurring behavior without jumping straight to personality fog
- Nornir can preserve a correction and regenerate affected output
- generated pages can be challenged back to source rows, evidence bundles, or corrections
