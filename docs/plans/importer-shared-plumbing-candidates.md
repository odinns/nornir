# Importer Shared Plumbing Candidates

Log candidates here when an importer repeats a pattern cleanly enough to extract later.

## Current candidates

- Run + artifact plumbing
  - `ImportRunExecutor` and `ImportArtifactWriter` are now used by ChatGPT, SMS, and Facebook.
  - This seam already looks real. Leave it alone for now unless another importer forces a cleaner contract.

- Canonical handoff boundary support
  - `SourcePageHandoffSupport` now serves both SMS and Facebook.
  - Good candidate for staying shared, with source-specific row counting kept local.

- File-backed archive helpers
  - Facebook added archive JSON traversal, attachment path normalization, and light metadata lookup.
  - Do not extract yet. Wait for Twitter or Instagram to prove which parts are genuinely common instead of merely both involving folders.

- Additive observation tables
  - SMS and Facebook both need per-source observation rows to keep reruns additive and partial exports non-destructive.
  - Worth revisiting once a third importer lands so the common contract is obvious rather than invented.
