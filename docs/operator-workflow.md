# Operator workflow

This is the current usable path for Gmail-first evidence work.

It does not create durable biography pages. It does not promote facts into Muninn tables. It builds a local review bundle, then gives a human operator a clean place to write private notes with provenance intact.

## Goal

Start with a bounded Gmail question and end with an ignored note that says:

- what claim or question you reviewed
- which import run and evidence bundle you used
- which `gmail_messages:{message_id}` refs support each candidate fact
- what contradicts the claim
- what context is still missing
- what to do next

The note is review material. It is not product output.

## 1. Import a narrow Gmail slice

Use the smallest useful Gmail query first:

```bash
php artisan import:gmail data/sources/gmail/credentials.json --query="from:someone@example.com OR to:someone@example.com"
```

Other useful shapes:

```bash
php artisan import:gmail data/sources/gmail/credentials.json --query="after:2014/01/01 before:2015/01/01"
php artisan import:gmail data/sources/gmail/credentials.json --query="subject:(invoice OR receipt) after:2010/01/01"
```

Keep the `Run id` printed by the import command. The evidence bundle command needs that id.

## 2. Backfill plaintext if bodies are thin

This command is not scoped to one import run. It checks every imported Gmail row with an HTML body and missing plaintext. Run the dry run first:

```bash
php artisan gmail:backfill-body-plain --dry-run
```

Then run the backfill:

```bash
php artisan gmail:backfill-body-plain
```

The real run writes normalized `body_plain` into already imported Gmail rows. It does not call Gmail.

## 3. Build the evidence bundle

Use the Gmail import run id:

```bash
php artisan evidence:gmail-important --run-id=123
```

The command reads canonical `gmail_messages` rows from that import slice and writes:

```text
data/reviews/gmail-important-evidence-run-123.json
```

Useful variants:

```bash
php artisan evidence:gmail-important --run-id=123 --limit=25
php artisan evidence:gmail-important --run-id=123 --rules=data/sources/gmail/important-rules.json
php artisan evidence:gmail-important --run-id=123 --json
```

The bundle is a review artifact. It includes full message bodies, labels, scoring reasons, proposed next actions, and provenance refs like `gmail_messages:msg-abc123`.

## 4. Inspect the bundle

Start with the bundle shape:

```bash
jq '{source_run_id, evidence_run_id, query, selection}' data/reviews/gmail-important-evidence-run-123.json
```

List the review queue without dumping whole message bodies into the terminal:

```bash
jq '.items[] | {received_at, from, subject, urgency, reason, next_action, provenance_ref}' data/reviews/gmail-important-evidence-run-123.json
```

Extract only the provenance refs:

```bash
jq -r '.items[].provenance_ref' data/reviews/gmail-important-evidence-run-123.json
```

Check for thin body extraction:

```bash
jq -r '.items[] | select((.body_plain // "") == "" and (.body_html // "") != "") | [.message_id, .subject] | @tsv' data/reviews/gmail-important-evidence-run-123.json
```

If that returns rows, run the plaintext backfill and rebuild the bundle.

## 5. Use read-only SQL only when needed

Most review work should happen from the JSON bundle. Use SQL when you need to locate a run, artifact, or provenance link.

Find recent Gmail import runs:

```sql
SELECT id, status, started_at, finished_at, input_scope
FROM runs
WHERE operation = 'gmail-import'
ORDER BY id DESC
LIMIT 10;
```

Find recent important-mail bundles:

```sql
SELECT id, run_id, locator, metadata, created_at
FROM run_artifacts
WHERE artifact_kind = 'gmail-important-evidence-bundle'
ORDER BY id DESC
LIMIT 10;
```

Check provenance for one evidence run:

```sql
SELECT output_target, evidence_ref, metadata
FROM provenance_links
WHERE run_id = 456
ORDER BY id
LIMIT 50;
```

Keep SQL read-only. Do not use `artisan tinker`, `php -r`, migrations, seeders, or ad-hoc Laravel bootstrap scripts for this workflow.

## 6. Write private operator notes

Write notes under:

```text
data/reviews/operator-notes/<topic-or-run>.md
```

`data/` is ignored. Keep it that way. Do not add real correspondence, evidence bundles, credentials, or operator notes to git.

Minimal note template:

```md
# <Claim or question>

claim/question:
source run:
bundle path:
evidence refs:

candidate facts:
-

contradictions:
-

missing context:
-

next action:
-
```

Use the evidence refs as the anchor for every candidate fact. A useful note says "this may be true because `gmail_messages:...` says X." A weak note says "seems important" and then wanders off into fog.

## 7. Preserve the refs

When you copy a fact into any later review, handoff, or draft, carry the provenance ref with it.

Good:

```text
Candidate fact: The contract question was still open on 2026-04-20.
Evidence: gmail_messages:msg-high-old
```

Not enough:

```text
Candidate fact: The contract question was still open.
Evidence: Gmail
```

The message id is the unit. Keep it attached, or the claim has lost its handle.

## Done means done

Stop when you have:

- a bounded Gmail import run id
- an evidence bundle under `data/reviews/`
- an ignored operator note under `data/reviews/operator-notes/`
- provenance refs copied into the note
- contradictions and missing context called out instead of smoothed over

This workflow is intentionally manual. The point is to make the current system useful without pretending the later Muninn workbench already exists.
