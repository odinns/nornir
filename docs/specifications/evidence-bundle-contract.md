# Evidence Bundle Contract

## Purpose

Define the first evidence bundle shape Nornir can rely on.

This is not a universal evidence framework. It is the contract for the current review bundle format, starting with Gmail important mail. Make the next bundle earn its differences instead of pretending this one solved every future case.

## Contract version

Every bundle must include:

```json
{
    "schema_version": 1
}
```

`schema_version` is an integer. Version `1` is the only supported version.

Adding fields inside a concrete bundle type is allowed only when the producer, tests, and this contract move together. Removing or renaming fields is a contract change.

## Required envelope

Every v1 evidence bundle must include these top-level fields:

- `schema_version`
- `bundle_type`
- `source_type`
- `source_run_id`
- `evidence_run_id`
- `generated_at`
- source boundary fields
- `selection`
- `items`

The envelope identifies what kind of bundle this is, which source slice it came from, which Muninn evidence run produced it, and how the items were selected.

`generated_at` must be an ISO 8601 timestamp.

`source_run_id` points to the source import run. `evidence_run_id` points to the derived Muninn run that wrote the bundle.

`selection` is the selection summary. It must explain the mode, limit when a limit exists, and matched count.

`items` is a list. An empty list is valid when the source boundary exists and no evidence matches the selection rules.

## Provenance

Every claim-bearing item must include `provenance_ref`.

Provenance refs use the existing `table:source-id` style:

```text
gmail_messages:msg-high-old
```

The ref must point at the canonical source row that supports the item. It is not a citation label and not free text.

When a bundle writes provenance links, the item `provenance_ref` and the stored provenance link must identify the same source row.

## Gmail important mail

`gmail-important-mail` is the first concrete bundle type.

Its top-level fields are:

- `schema_version`
- `bundle_type`
- `source_type`
- `source_run_id`
- `evidence_run_id`
- `generated_at`
- `account_email`
- `source_set_ids`
- `query`
- `selection`
- `items`

The Gmail source boundary is:

- `account_email`: the Gmail account imported
- `source_set_ids`: the bounded `gmail_source_sets` rows used for the slice
- `query`: the Gmail query captured by the import run

The Gmail selection summary is:

- `mode`: `important-mail-score`
- `limit`: the maximum number of selected messages
- `matched_count`: the number of emitted items

Each Gmail item must include:

- `message_id`
- `thread_id`
- `from`
- `to`
- `cc`
- `subject`
- `received_at`
- `urgency`
- `reason`
- `next_action`
- `confidence`
- `labels`
- `snippet`
- `body_plain`
- `body_html`
- `provenance_ref`

`provenance_ref` must be `gmail_messages:{message_id}`.

## Review checklist

- the bundle declares `schema_version: 1`
- top-level keys match the concrete bundle type
- the source boundary is bounded enough to rerun or inspect the slice
- selection explains why these items appeared
- every claim-bearing item has a provenance ref
- empty `items` means no matches, not a broken source boundary

## Acceptance checks

- a manual reviewer can inspect the bundle without guessing its source slice
- Muninn can consume the bundle without inferring hidden fields
- provenance can be followed from each item back to canonical rows
