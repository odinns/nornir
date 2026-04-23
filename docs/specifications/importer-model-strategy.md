# Importer Model Strategy

## Purpose

Define the shared Laravel model rules for Nornir importer tables so new importer model work stays boring, consistent, and source-honest.

## Responsibilities

- map importer tables to explicit Eloquent models without inventing fake sameness
- keep canonical, derived, and external-boundary tables visibly different
- standardize naming, relationships, casts, and timestamp handling
- say where shared base models or traits help and where importer-specific code should stay local

## Inputs

- source-specific importer specs
- `importer-framework.md`
- `mysql-storage-contract.md`
- existing table patterns from `chatgpt_*`, `gmail_*`, `apple_health_*`, `apple_messages_*`, `linkedin_*`, `twitter_*`, `instagram_*`, `facebook_*`, `fidonet_*`, and `media_files`

## Outputs

- one Eloquent model per Nornir-owned importer table worth querying or relating to
- explicit exceptions for external canonical boundaries and bridge tables
- a stable contract for future importer model tasks

## Storage and state

Importer model strategy follows storage truth, not aesthetics:

- source-prefixed canonical tables stay source-prefixed in MySQL
- derived tables stay visibly derived in both table and model naming
- shared operational tables like `runs` and `provenance_links` do not become importer models
- external canonical systems keep their own truth boundary; Nornir models represent Nornir-owned bridge or derived tables, not mirrored fantasy copies

Evidence from current specs:

- ChatGPT uses separate archive, conversation, node, message, message-part, and asset tables because graph edges matter
- Gmail uses separate account, thread, message, label, and attachment tables because ids and joins matter
- media collection uses one bridge table, `media_files`, because source truth already lives in `monique`
- FidoNet keeps external canonical truth outside Nornir and limits Nornir tables to derived or integration state

## Processing rules

### Naming

- Table-backed model names are singular StudlyCase forms of the real table meaning, not generic wrappers
- Use source prefix in class names when the table prefix carries real ownership context: `ChatGptConversation`, `GmailMessage`, `LinkedinConversation`
- Use plain domain names only when the table itself is intentionally cross-source or bridge-shaped, like `MediaFile`
- Derived models must say so in the class name when the table is derived, cleaned, projected, or transcript-oriented
- Do not create fake universal models like `ImportedMessage`, `ImportedAccount`, or `ImporterRecord`

### Table mapping

- Set `$table` explicitly on importer models. Do not rely on Laravel pluralization for prefixed table names
- Use the default primary key contract unless the table spec says otherwise: integer or bigint `id`, incrementing
- Keep importer models on the default Nornir connection unless the table is explicitly an external canonical boundary, in which case use a clearly separate external model or query path

### Relationships

- Define Eloquent relationships only for edges the schema already owns
- Prefer ordinary `belongsTo`, `hasMany`, `hasOne`, and `belongsToMany` semantics over polymorphic tricks
- Mirror importer-local structure directly:
- ChatGPT archive -> conversations -> nodes/messages/message parts/assets
- Gmail account -> threads -> messages -> labels/attachments
- LinkedIn conversation -> messages
- Keep cross-source linking out of importer models. That belongs in downstream evidence, Muninn, or provenance layers
- Do not hide missing foreign keys behind inferred relationships. If relation integrity matters, store the key and model it honestly

### Casts

- Cast canonical UTC timestamps to immutable datetimes
- Cast date-only evidence like `event_date` to immutable dates
- Preserve raw source epoch or source timestamp strings in their own columns when the source gives them; do not collapse them into one prettified field
- Use scalar casts for booleans, integers, and arrays only when the column semantics are stable and obvious
- Use JSON columns for naturally nested source payload fragments or sparse metadata bags, not for data that should be relational
- If a value needs frequent joins, uniqueness rules, or scoped queries, give it a table, not a JSON blob

### Timestamp handling

- Nornir-owned tables with `created_at` and `updated_at` use normal Eloquent timestamps
- Source-observed times never piggyback on `created_at` or `updated_at`; they get explicit columns like `sent_at`, `recorded_at`, `fs_created_at`, or `source_created_at`
- Canonical importer datetime columns represent UTC instants, matching `importer-framework.md` and `mysql-storage-contract.md`
- When source precision is partial, preserve that honestly. Example: `event_date` stays a date, not fake midnight timestamp
- Disable `$timestamps` only when the table truly does not own Laravel timestamps or is intentionally read-only

### JSON attributes

- JSON is allowed for raw structured fragments, header bags, payload metadata, and source-native nested shapes that would be silly to explode on day one
- JSON is not allowed as junk drawer replacement for obvious child tables like Gmail labels or ChatGPT message parts
- Prefer plain `array` casts first. Add custom cast classes only when multiple models need the same behavior and the shape is stable enough to deserve code
- Keep JSON attribute names literal and source-honest. Do not rename them into generic abstraction sludge

### Base models and traits

Shared base models help only with boring cross-source defaults:

- explicit `$guarded` or `$fillable` policy
- common date serialization policy
- strict lazy-loading or discarded-attribute protection
- shared helpers for provenance-facing identifiers when multiple importer models truly need the same shape

Traits help only when repetition is both real and source-agnostic:

- common UTC datetime helpers
- common source scope helpers
- shared derived-table markers

Keep behavior importer-specific when:

- naming depends on source vocabulary
- identity rules depend on source ids or archive boundaries
- JSON payload interpretation is source-specific
- derived accessors would hide source nuance
- relationship trees differ by source

Rule of thumb: if only one importer needs it, keep it in that importer. If three importers need the same boring behavior with same semantics, share it.

## Forbidden behavior

- no abstract `ImporterModel` base class stuffed with source business logic
- no generic trait that pretends Gmail labels, ChatGPT nodes, and LinkedIn messages are same thing
- no use of `created_at` as source event time
- no JSON blobs replacing relational structure that current specs already separate into tables
- no cross-source relationship web inside importer models
- no mirroring external canonical databases into fake local Eloquent symmetry without explicit spec support

## Interfaces

- importer-specific model classes under app model namespaces
- importer-specific relations matching real table edges
- optional thin shared base model or traits for boring repeated conventions only

## Validation and testing

- architecture tests should enforce importer model placement and block cross-layer drift
- each importer Eloquent model slice must include a lightweight model contract test for explicit table mapping, important casts, and declared Eloquent relationships
- each importer Eloquent model slice with a real graph must include at least one DB-backed feature test over imported fixture data that traverses the persisted Eloquent relationship graph forward and backward
- DB-backed Eloquent graph tests should assert concrete fixture truths, not only counts
- tests should verify external-boundary exceptions do not accidentally write into external canonical systems

## Review checklist

- class names match real table ownership and meaning
- `$table` is explicit for prefixed importer tables
- source timestamps are separate from Laravel timestamps
- JSON is used for nested payload truth, not relational laziness
- shared base or trait code is boring plumbing, not hidden importer policy
- external canonical boundaries stay explicit
- Eloquent relationship coverage proves the persisted graph in both directions when the importer owns a multi-table graph

## Acceptance checks

- future importer model tasks can implement consistent Eloquent models without inventing policy
- model code will reflect current schema evidence instead of flattening sources into fake sameness
- shared conventions are strong enough to reduce drift, small enough to avoid mega-abstractions
