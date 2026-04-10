# Nornir Detailed Specifications

This directory turns the top-level system spec into implementation-grade contracts.

Read in this order:

1. `spec-conventions.md`
2. `storage-and-path-conventions.md`
3. `coding-standards-and-quality-gates.md`
4. `testing-and-tdd-strategy.md`
5. `ai-and-mcp-architecture.md`
6. subsystem specs
7. source navigation and importer specs

Ground rules:

- MySQL is canonical for imported source material.
- `wiki/` is compiled markdown output, not source truth.
- `data/sources/` is non-versioned local source material.
- `data/` is non-versioned operational output, including local source drops under `data/sources/`.
- external raw material stays outside git.
- importer means source-specific normalization into Nornir.
- Mimir is intentionally last.
