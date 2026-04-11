# Remaining Work

The initial backend spine is in place.

Completed phases:

- Phase 0: Laravel scaffold and quality gates
- Phase 1: shared operational backbone
- Phase 2: intake boundary
- Phase 3: ChatGPT importer plus CLI
- Phase 4: compile-facing ChatGPT handoff
- Phase 5: first hardening pass

What remains is no longer bootstrap work.
It is product work built on top of a working intake/import/handoff spine.

## Current state

Nornir can now:

- record bounded intake requests
- normalize ChatGPT exports into canonical MySQL tables
- track runs, events, artifacts, and provenance
- expose a compile-facing ChatGPT handoff from canonical rows
- run importer and handoff flows from Artisan commands

## Remaining roadmap

### 1. Additional source importers

Add the next real source importer using the same pattern as ChatGPT:

- bounded intake
- source-specific canonical tables
- importer CLI
- idempotent reruns
- compile-facing handoff

Good candidates:

- FidoNet
- Gmail
- SMS
- media collection

The order should be chosen by source value and tractability, not by trying to boil the ocean.

### 2. Wiki compilation

Build the first real compiler stage that consumes handoff contracts and writes compiled markdown to `wiki/`.

That work should include:

- page type selection
- deterministic slugs and paths
- provenance-preserving output
- rerun-safe overwrites
- no writes outside `wiki/`

This should start with source pages, not Muninn or Huginn synthesis.

### 3. Muninn processing

Once multiple sources exist and source-page compilation is real, build the evidence-first Muninn passes:

- extraction
- event/timeline shaping
- evidence bundling
- provenance-preserving outputs

Do not jump here early just because it sounds grander.

### 4. Huginn processing

Only after canonical imports and evidence-first biography work are stable:

- pattern synthesis
- behavioral observations
- supported interpretive outputs

Huginn stays downstream of real evidence, not vibes.

### 5. Mimir

The web layer is still intentionally late.

When it starts, it should be reading stable data products rather than forcing backend boundaries to bend around UI convenience.

### 6. Operational cleanup

There is still some deliberate cleanup left:

- make MySQL test database resets less flaky in isolated runs
- improve operator-facing rerun messaging
- add more architecture tests around the newer handoff seam
- keep deleting any abstraction that starts dressing like a framework

## Working rules for the remaining phases

- every phase must end with something runnable or inspectable
- every new importer must be exercised on real source data before the phase is considered done
- keep contracts explicit, but do not persist review artifacts unless they earn their keep
- do not overengineer shared infrastructure in anticipation of hypothetical future sources
