# Nornir — System Specification (v1)

## purpose

Nornir is a Laravel-based system for ingesting, processing, understanding, and presenting personal source material.

It replaces ad-hoc scripts with a coherent application that supports:

- intake of raw material
- structured ingestion
- biography reconstruction
- personality modeling
- query and exploration
- visual presentation of timelines, connections, and arcs

Nornir is not a wiki.
It is a system that continuously processes and relates life data over time.

---

# core concepts

## Nornir
The overall system.

Responsible for:
- orchestration
- lifecycle management
- coordinating flows across all subsystems

---

## Muninn (biography)

The evidence-based layer.

Responsible for:
- sources
- extraction
- events
- timeline
- people
- places
- arcs
- provenance

Principles:
- all outputs must be traceable to sources
- interpretation is constrained
- chronology is primary

---

## Huginn (personality)

The interpretive layer.

Responsible for:
- patterns
- preferences
- behaviors
- working style
- recurring themes
- evolving self-models

Principles:
- interpretation is allowed
- outputs must be supported by evidence
- synthesis across sources is expected

---

## Mimir (web layer)

The presentation and interaction surface.

Responsible for:
- UI
- query surface
- assembling data for views
- exposing system knowledge to the user

Mimir does not contain core domain logic.

---

## Heimdallr (external access)

The controlled boundary to external sources.

Responsible for:
- reading external data sources
- searching remote/project contexts
- fetching bounded content
- enforcing access rules
- passing data into intake

Principles:
- read-only in v1
- strictly bounded access
- explicit allow rules
- no uncontrolled traversal

---

# system lifecycle

## 1. intake

Raw material enters the system.

Examples:
- file imports
- archive uploads
- synced directories
- externally fetched material

Output:
- intake records
- stored raw files
- metadata

---

## 2. import

Raw material is normalized into internal records.

Examples:
- SMS message
- email
- document
- conversation fragment

Output:
- source items
- normalized content
- import runs
- metadata

Notes:
- imported raw and normalized source truth lives canonically in MySQL
- generated markdown remains on disk
- raw source material and generated outputs are not version controlled
- local `data/sources/` may hold non-versioned source material such as archives, CVs, personality tests, or site dumps

---

## 3. Muninn processing

Biography extraction.

Produces:
- events
- time ranges
- people
- places
- arcs
- relationships

All outputs must maintain provenance.

---

## 4. Huginn processing

Personality analysis.

Produces:
- observations
- patterns
- behavioral models
- thematic clusters

Outputs must link back to supporting evidence.

---

## 5. presentation (Mimir)

System is exposed through a web interface.

Provides:
- timeline
- graph views
- arcs
- personality insights
- source exploration
- query interface

---

# architectural stance

Use Laravel conventions.

Do NOT introduce artificial domain folder structures.

DDD is expressed through:
- naming
- boundaries
- responsibilities
- dependency direction

---

# application structure

app/
  Http/
    Controllers/Mimir/
    Requests/Mimir/
    Resources/Mimir/

  Models/

  Services/
    Nornir/
    Muninn/
    Huginn/
    Mimir/
    Heimdallr/
    Intake/
    Import/
    Graph/

  Actions/
    Intake/
    Import/
    Muninn/
    Huginn/
    Heimdallr/

  Jobs/
    Intake/
    Import/
    Muninn/
    Huginn/
    Heimdallr/

  Data/
    Muninn/
    Huginn/
    Heimdallr/
    Shared/

  Support/
    Shared/

---

# responsibility boundaries

## Services/Intake
- accept raw material
- store files
- create intake records

## Services/Import
- parse raw data
- normalize into source items
- track import runs

## Services/Muninn
- build timeline
- extract events and arcs
- maintain provenance

## Services/Huginn
- generate observations
- detect patterns
- synthesize models

## Services/Mimir
- assemble view data
- power UI queries
- aggregate domain outputs

## Services/Heimdallr
- access external sources
- fetch bounded content
- enforce access policies
- hand data to intake

## Services/Nornir
- orchestrate flows
- coordinate rebuilds
- manage system-level operations

---

# dependency rules

Allowed:

- Controllers → Services/Mimir
- Services/Mimir → Services/Muninn, Services/Huginn
- Services/Nornir → Services/Import, Services/Muninn, Services/Huginn
- Services/Heimdallr → Services/Intake

Forbidden:

- Muninn → Heimdallr
- Huginn → Heimdallr
- Controllers → raw models for logic
- Heimdallr → UI logic

---

# data strategy

Hybrid approach.

## file-backed
- generated markdown
- generated operational artifacts
- optional local raw staging, not version controlled

## database-backed
- sources
- intake batches
- import runs
- source items
- events
- arcs
- observations
- patterns
- relations

---

# core models

## Source
Represents a source origin.

## IntakeBatch
Represents a raw intake operation.

## SourceItem
Normalized unit of content.

## ImportRun
Tracks imports.

## BiographyEvent
Timeline event.

## Arc
Narrative strand.

## Person
Entity.

## Place
Entity.

## Observation
Personality insight.

## Pattern
Higher-level synthesis.

## Connection
Graph relation.

## EvidenceLink
Links derived data to source material.

---

# provenance

Every derived object must be traceable to:

- source item(s)
- import/process run
- origin file or fragment

Applies to:
- events
- arcs
- observations
- patterns

---

# Heimdallr scope (v1)

Include:
- external source registration
- bounded read access
- search where available
- fetch operations
- import into intake
- run tracking

Exclude:
- write operations
- full synchronization
- autonomous crawling
- complex auth models

---

# Mimir views (v1)

## dashboard
- recent activity
- quick navigation

## timeline
- infinite scroll
- chronological events
- filters

## event detail
- event data
- connections
- source trace

## arcs
- grouped narrative strands

## connections
- graph view

## personality
- observations
- patterns
- evidence

## sources
- intake and import overview

## query interface
- system-level exploration

---

# AI integration

AI usage must be:

- orchestrated by code
- logged
- repeatable
- tied to runs and outputs

Use cases:
- extraction
- classification
- summarization
- pattern detection
- synthesis

AI outputs are:
- candidates
- interpretations
- never silent truth

---

# processing modes

## full rebuild
Reprocess everything.

## incremental
Process new data only.

## targeted
Reprocess subset.

---

# guiding principles

- keep biography and personality separate
- preserve provenance everywhere
- favor clarity over cleverness
- avoid over-engineering
- keep system inspectable
- prioritize usefulness over completeness

---

# summary

Nornir is a system that:

- ingests raw life data
- structures it into biography (Muninn)
- interprets it into personality (Huginn)
- exposes it through a web layer (Mimir)
- accesses external sources through a controlled boundary (Heimdallr)

The system is designed to evolve continuously rather than reach a fixed final state.
