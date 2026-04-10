# ChatGPT To Nornir Importer

## Goal

Import ChatGPT export conversations into canonical MySQL tables with graph fidelity and derived transcript support.

## Canonical source

ChatGPT export conversation JSON files.

## Inputs

- export path
- optional additional export roots when the source is split across an approved bounded list of local paths
- optional archive label
- dry-run or validate-only flags

## Output structure

- canonical `chatgpt_*` tables
- run artifacts under `data/imports/chatgpt/` and `data/runs/`
- downstream source-page handoff

## MySQL storage model

- `chatgpt_archives`
- `chatgpt_conversations`
- `chatgpt_nodes`
- `chatgpt_messages`
- `chatgpt_message_parts`
- `chatgpt_assets`
- derived visible transcript tables as clearly derived

## Data model

Use stable export identifiers for conversations, nodes, messages, and assets.

## Import rules

- preserve graph structure
- preserve roles
- derive visible transcript rows separately
- read directly from approved bounded local paths when available instead of requiring a copied staging mirror inside Nornir
- when multiple local roots are configured, treat them as an explicit allowlist and record which root supplied the imported archive or files

## Incremental behavior

- reruns are idempotent by archive, conversation, and message identity

## Validation

- graph linkage integrity
- message-role preservation
- transcript derivation consistency

## Wiki compilation handoff

- compile source summaries from normalized rows, not raw file rescans

## Forbidden behavior

- no flattening by default
- no summarization during import
- no implicit filesystem wandering outside the accepted root path or path list
- no mandatory raw-source copy into `data/` just to satisfy the importer

## Review checklist

- graph truth survives import
- derived transcript is visibly derived

## Acceptance checks

- transcript pages can be built without losing node lineage
