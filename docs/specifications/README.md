# Nornir Detailed Specifications

This directory turns the top-level system spec into implementation-grade contracts.

Read in this order:

1. `spec-conventions.md`
2. `storage-and-path-conventions.md`
3. `mysql-storage-contract.md`
4. `orchestration-runs-jobs-and-provenance.md`
5. `coding-standards-and-quality-gates.md`
6. `testing-and-tdd-strategy.md`
7. `ai-and-mcp-architecture.md`
8. subsystem specs
9. source navigation and importer specs

Ground rules:

- MySQL is the canonical working store for imported source material unless a source already has a justified external canonical database.
- `wiki/` is compiled markdown output, not source truth.
- `data/sources/` is non-versioned local source material.
- `data/` is non-versioned operational output, including local source drops under `data/sources/`.
- external raw material stays outside git.
- importer means source-specific normalization into Nornir.
- Mimir is intentionally last.
