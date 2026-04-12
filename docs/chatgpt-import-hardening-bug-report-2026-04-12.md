# ChatGPT Import Hardening Bug Report

Date: 2026-04-12

## Summary

The ChatGPT importer works on the current fixture set, but a real export at `/Users/odinn/Projects/odinns/llm-wiki/raw/history/chatgpt/23e42f0d98ef2c6ad5ee63e1d07055dc539f8adf230ceba3e812086629d0a696-2026-04-06-08-56-06-8a2e8dc02fc3447e910952404cbe5be8` shows several untested and potentially lossy content shapes.

This is not a confirmed data-corruption bug yet. It is a hardening gap with credible failure modes.

## Findings

### 1. Many real messages have `content` but no `parts`

Real export census:

- `42,312` messages total
- `7,412` messages with `content` present but no `content.parts` array

Current importer behavior in [ImportChatGptConversationsAction.php](/Users/odinn/Projects/odinns/nornir/app/Actions/Import/ImportChatGptConversationsAction.php):

- stores the `chatgpt_messages` row
- stores no `chatgpt_message_parts` when `parts` is missing or not an array

Risk:

- content-bearing messages may appear canonically present while their usable content is silently absent from `chatgpt_message_parts`
- downstream compilers may treat these as empty messages

### 2. Real content types are broader than the fixtures

Observed real `content.content_type` values include:

- `text`
- `code`
- `multimodal_text`
- `thoughts`
- `reasoning_recap`
- `execution_output`
- `app_pairing_content`
- `user_editable_context`
- `tether_browsing_display`
- `tether_quote`
- `system_error`
- `sonic_webpage`
- `super_widget`

Current tests mostly exercise `text` and `multimodal_text`.

Risk:

- importer behavior for newer or stranger content types is accidental rather than specified

### 3. Structured non-string parts are common

Real export census:

- `7,025` non-string parts

Most examples are `multimodal_text` asset objects like:

- `asset_pointer`
- `content_type`
- dimensions
- nested metadata

Current importer behavior:

- stores text parts when a part is a non-empty string
- stores assets when a part is an array with a non-empty `asset_pointer`
- silently ignores any other structured part shape

Risk:

- structured content without `asset_pointer` may be dropped without any explicit contract or test coverage

### 4. Duplicate `message.id` values exist across conversations

Real export census:

- `150` duplicate `message.id` values across the export

Current schema keys messages by `(chatgpt_conversation_id, message_id)`, which is probably correct.

Risk:

- this is safe today, but not protected by an explicit regression test
- a future simplification to global `message_id` identity would break valid imports

### 5. Real export root contains a lot of sidecar material

Observed alongside `conversations-*.json`:

- `chat.html`
- many top-level asset files
- `dalle-generations/` with generated media

Current importer behavior:

- only scans `conversations-*.json`

This is fine for phase 1, but the ignore behavior is not called out by tests.

Risk:

- future changes to file discovery could accidentally widen scope or become order-dependent

## Recommended Hardening Tests

Add these first:

1. A real-shape message with `content` but no `parts`, asserting the importer succeeds and preserves the `chatgpt_messages` row without inventing `chatgpt_message_parts`.
2. Two conversations reusing the same `message.id`, asserting both canonical message rows are preserved.

Good next tests:

3. A structured non-string part without `asset_pointer`, asserting the current ignore behavior explicitly.
4. A local-path export root containing valid `conversations-*.json` plus sidecar files, asserting only conversation JSON files are imported.

## Why This Matters

The importer currently looks correct on the happy-path fixtures. The real export says the happy path is only part of the map.

Without these tests, the likely failure mode is not a loud crash. It is quieter and worse: messages import successfully, but some content shapes vanish into canonical rows like socks into a black hole.
