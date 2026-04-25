# Nornir System Specification

Nornir is a Laravel-based system for importing, processing, understanding, and presenting personal source material.

It replaces ad-hoc archive scripts with a coherent local application that supports:

- bounded intake of private and public source material
- source-specific import into canonical MySQL tables
- auditable runs, artifacts, and provenance links
- cross-source search
- evidence-bound biography reconstruction
- evidence-supported personality and pattern work
- eventual web presentation through Mimir

Nornir is not a wiki. `wiki/` is generated output. The system of record is the database plus the original source archives and external references.

## Current Implementation

The current app is backend-first and CLI-first.

Implemented:

- Laravel 13 application
- MySQL canonical storage
- intake records
- run/event/artifact/provenance infrastructure
- importer commands and actions
- source handoff builders
- Scout/Meilisearch search projection
- Gmail OAuth/API import and triage
- Pest feature/unit/architecture tests
- PHPStan, Pint, and Rector gates

Not yet implemented as a polished product surface:

- Mimir web UI
- durable Muninn biography workbench
- full Huginn personality synthesis
- generic public importers for Monique and FidoNet companion databases

## Core Concepts

### Nornir

The overall application.

Responsible for:

- orchestration
- lifecycle management
- coordinating source import, search, evidence, and presentation flows

### Intake

The shallow boundary where source material enters the system.

Intake records:

- source type
- access mode
- concrete source locator
- accepted scope
- importer options
- review manifest path

Intake does not normalize source content. It records what was accepted and hands a bounded payload to the importer.

### Import

The source-specific normalization layer.

Importers:

- read one accepted source shape
- preserve source ids and timestamps
- write canonical source-prefixed tables
- record import runs and artifacts
- remain idempotent across reruns
- avoid destructive deletion when later exports are thinner
- emit enough scope for a handoff

Current source families:

- ChatGPT
- Facebook
- X/Twitter
- LinkedIn
- Instagram
- Gmail
- Apple Messages
- Apple Health
- Wayback Machine
- media collection bridge from Monique/mostly-unique
- FidoNet bridge from GoldED/FidoNet

### Source Handoffs

A handoff is a bounded compile/evidence contract over canonical rows.

It says:

- which source is involved
- which run owns the slice
- which canonical tables matter
- which row set or source-set ids define the boundary
- how many rows are inside the slice

The handoff is not source data. It is the label that lets later biography tooling work from the right canonical slice without rescanning raw archives or querying too broadly.

### Muninn

The evidence-based biography layer.

Responsible for:

- dated facts
- events
- timeline candidates
- people
- places
- arcs
- relationships
- evidence bundles

Principles:

- all claims must be traceable to sources
- chronology is primary
- contradictions are surfaced, not smoothed away
- interpretation is constrained

### Huginn

The interpretive personality and pattern layer.

Responsible for:

- recurring behaviors
- preferences
- working style
- personal patterns
- thematic clusters
- evolving self-models

Principles:

- interpretation is allowed
- outputs must be evidence-supported
- synthesis across sources is expected
- Huginn must not overwrite Muninn's factual boundary

### Mimir

The eventual presentation and interaction surface.

Responsible for:

- UI
- query surface
- timeline and source exploration
- graph and arc views
- assembling view data from backend contracts

Mimir does not own core domain logic. It waits for stable backend data products instead of bending the architecture around an early UI.

### Heimdallr

The controlled boundary to external sources.

Responsible for:

- bounded read access
- source registration
- fetch operations
- access rules
- handoff into intake

Principles:

- read-only by default
- explicit allow rules
- no uncontrolled traversal
- no autonomous crawling

## System Lifecycle

### 1. Intake

Raw material or source descriptors enter the system.

Examples:

- local archive paths
- extracted provider exports
- Apple Messages `chat.db`
- Apple Health XML
- Gmail API query scopes
- Wayback host/prefix/exact URL scopes
- external database connection descriptors

Output:

- intake record
- review manifest
- importer dispatch payload

### 2. Import

The source-specific importer normalizes the accepted source into canonical rows.

Examples:

- Gmail message and thread rows
- Facebook Messenger threads and posts
- LinkedIn positions, messages, recommendations, and endorsements
- Twitter authored tweets and media refs
- Apple Messages conversations and participants
- Apple Health records and workouts
- Wayback captures

Output:

- source-prefixed canonical tables
- source-set or observation rows where needed
- run/event/artifact rows
- provenance links where applicable

### 3. Search Projection

Search builders turn canonical rows into disposable `search_documents` rows and Scout/Meilisearch documents.

Search projection is derived state. Rebuild it when the canonical rows or builders change.

### 4. Handoff

`handoff:*` commands produce bounded `WikiCompilationHandoffData` payloads from canonical rows.

The downstream layer should consume handoffs instead of raw archives.

### 5. Muninn Processing

Muninn resolves evidence into biography-facing structures:

- event candidates
- timelines
- people and place references
- arcs
- evidence bundles

This layer is specified and partly scaffolded, but the durable workbench is still upcoming.

### 6. Huginn Processing

Huginn synthesizes evidence-supported patterns and personality observations.

This layer comes after the evidence contracts are solid enough to keep interpretation honest.

### 7. Presentation

Mimir eventually exposes the system through a web UI.

Until then, the useful surfaces are CLI commands, MySQL, generated handoffs, search, and review artifacts.

## Data Strategy

Tracked in git:

- application code
- migrations
- tests
- configuration templates
- specs and docs

Never tracked in git:

- raw archives
- extracted exports
- OAuth tokens
- credentials
- generated markdown
- run artifacts
- database dumps

Local ignored roots:

- `data/`
- `wiki/`

Canonical imported source truth lives in MySQL. Generated markdown is output, not source truth.

## Application Structure

Use Laravel conventions. Do not add artificial domain architecture when the framework already gives good places to put things.

Current important areas:

- `app/Actions/Import/`
- `app/Actions/Intake/`
- `app/Actions/Gmail/`
- `app/Console/Commands/`
- `app/Data/`
- `app/Models/`
- `app/Search/`
- `app/Services/Gmail/`
- `app/Services/Nornir/`
- `app/Services/Wayback/`
- `database/migrations/`
- `tests/Feature/`
- `tests/Unit/`
- `tests/Architecture/`

## Dependency Rules

Allowed:

- commands call actions
- import actions write canonical source tables
- handoff actions read canonical rows
- search builders read canonical rows and write derived search documents
- Muninn consumes canonical rows and handoffs
- Huginn consumes evidence-supported outputs
- Mimir consumes backend contracts

Forbidden:

- importers writing biography/personality conclusions
- Muninn or Huginn fetching raw external data directly
- source-specific branching monoliths
- Mimir owning core domain rules
- generated claims without provenance

## Provenance

Every derived or claim-bearing output must be traceable to:

- source row or evidence bundle
- import/process run
- origin file, API scope, or external reference

Applies to:

- events
- arcs
- observations
- patterns
- compiled pages
- review bundles

## AI Integration

AI usage must be:

- orchestrated by code
- logged
- repeatable enough to audit
- tied to runs and outputs
- constrained by evidence

AI outputs are candidates and interpretations. They are never silent truth.

## Guiding Principles

- keep biography and personality separate
- preserve provenance everywhere
- keep source boundaries explicit
- import broadly enough to preserve truth, then select downstream
- favor clarity over cleverness
- prefer source-specific correctness over fake universal models
- prioritize usefulness over completeness
