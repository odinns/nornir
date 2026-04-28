# Nornir Detailed Specifications

This directory turns the top-level system spec into implementation-grade contracts.

Nornir is no longer documentation-only. The current app has working CLI importers, canonical MySQL tables, intake/run/provenance plumbing, source handoff builders, and Scout search projection. Treat these specs as living contracts: when implementation changes source scope, table shape, command behavior, or handoff boundaries, update the relevant spec in the same slice.

Read in this order:

1. `spec-conventions.md`
2. `storage-and-path-conventions.md`
3. `mysql-storage-contract.md`
4. `importer-model-strategy.md`
5. `orchestration-runs-jobs-and-provenance.md`
6. `coding-standards-and-quality-gates.md`
7. `testing-and-tdd-strategy.md`
8. `ai-and-mcp-architecture.md`
9. `judgment-layer-contracts.md`
10. `evidence-bundle-contract.md`
11. subsystem specs
12. source navigation and importer specs

Implemented importer families today:

- ChatGPT exports
- Facebook exports
- X/Twitter exports
- LinkedIn exports
- Instagram exports
- Gmail API
- Apple Messages `chat.db`
- Apple Health `export.xml` / `eksport.xml`
- Wayback Machine CDX captures
- media collection bridge from the unpublished Monique/mostly-unique database
- FidoNet bridge from an unpublished GoldED/FidoNet database

Ground rules:

- MySQL is the canonical working store for imported source material unless a source already has a justified external canonical database.
- `wiki/` is compiled markdown output, not source truth.
- `data/sources/` is non-versioned local source material.
- `data/` is non-versioned operational output, including local source drops under `data/sources/`.
- external raw material stays outside git.
- importer means source-specific normalization into Nornir.
- source handoffs are bounded compile/evidence contracts over canonical rows.
- judgment records capture decisions, observations, contradictions, corrections, and promoted outputs as derived state with provenance.
- Mimir is intentionally last; current operation is CLI/backend-first.
- Monique/media-collection and FidoNet are real local integrations, but public users need the companion databases before those commands are useful.
