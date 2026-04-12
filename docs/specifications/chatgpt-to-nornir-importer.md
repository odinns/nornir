# ChatGPT To Nornir Importer

## Goal

Import ChatGPT export conversations into canonical MySQL tables with graph fidelity and timeline-grade timestamps.
The importer preserves broad canonical truth. Biography relevance is applied downstream, not by throwing data away during import.

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
- preserve raw float source times and normalized UTC datetimes for chronology work
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
- mark ChatGPT handoff as broad canonical evidence; downstream biography compilation must apply a relevance filter before treating chats as biography material

## Forbidden behavior

- no flattening by default
- no summarization during import
- no implicit filesystem wandering outside the accepted root path or path list
- no mandatory raw-source copy into `data/` just to satisfy the importer

## Review checklist

- graph truth survives import
- normalized UTC datetimes exist for conversations and messages
- derived transcript is visibly derived
- handoff makes the biography-filter requirement explicit

## Acceptance checks

- transcript pages can be built without losing node lineage
