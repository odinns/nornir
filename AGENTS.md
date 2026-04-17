# Repository Guidelines

## Project Structure & Module Organization

This repository currently holds the system and implementation specs for Nornir. Start with [docs/nornir-spec.md](/Users/odinn/Projects/odinns/nornir/docs/nornir-spec.md), then read [docs/specifications/README.md](/Users/odinn/Projects/odinns/nornir/docs/specifications/README.md). Detailed backend contracts live in `docs/specifications/`, including subsystem specs like `muninn-biography-pipeline.md` and source importer specs such as `chatgpt-to-nornir-importer.md`.

Planned application code MUST follow Laravel conventions: `app/`, `config/`, `database/`, `resources/`, `routes/`, and `tests/`. Generated and source data must stay out of git: `data/` and `wiki/` are ignored. Use `data/sources/` for local non-versioned source material, from single files like CVs and personality tests to larger dumps such as a Tantraviking site snapshot.

## Build, Test, and Development Commands

There is no runnable Laravel app yet, so current work is documentation-first.

- `rg -n "term" docs/specifications` searches the spec set quickly.
- `git status --short` shows pending changes.

Once the app scaffold exists, use these defaults:

- `composer test` runs the Pest suite.
- `./vendor/bin/pest` runs all tests.
- `./vendor/bin/pest --group=architecture` runs architecture tests.
- `./vendor/bin/pint` formats code.
- `./vendor/bin/phpstan analyse` runs static analysis.
- `./vendor/bin/rector process` applies approved refactors.

## Coding Style & Naming Conventions

Follow Laravel conventions, 4-space indentation, and clear literal names. Prefer `Import` over `Ingest` in new code. Keep source-specific logic local to its importer; do not build giant switchboard commands. Prompt and skill assets MUST be versioned files, not inline strings in jobs or controllers.

## Testing Guidelines

Pest is the default framework. Pest architecture tests are mandatory and MUST enforce subsystem boundaries, dependency direction, and forbidden cross-layer access. Name tests by behavior, for example `it_imports_chatgpt_conversations_idempotently` or `it_blocks_unbounded_heimdallr_access`.

## Commit & Pull Request Guidelines

This repo has no commit history yet, so adopt Conventional Commits from the start: `docs: add importer specs`, `feat: scaffold import runs`, `test: add Muninn architecture rules`. Keep commits small and single-purpose.

PRs MUST explain the change, name affected specs or modules, note testing performed, and call out boundary or storage-rule changes explicitly. Include screenshots only when Mimir UI work begins.

## Architecture Notes

MySQL is canonical for imported source material. `wiki/` is compiled markdown output. Heimdallr is read-only. Muninn is evidence-first. Huginn may synthesize, but never without traceable support.

## Contributor Behavior

Push back on requests that conflict with the specs, blur important boundaries, or introduce obvious architectural drift. Do not comply by default just because a request is recent or emphatic. Call out the conflict plainly, explain the better path, and only bend the rules when the change is deliberate and the tradeoff is explicit.

## Task Management

Use Runes as the active backlog for Nornir. Load the external skill at `/Users/odinn/Projects/runes/SKILL.md` for task capture, task updates, attachments, and project bootstrap, and treat that skill as the source of truth for writing into the Runes repo.

The canonical project backlog lives in `/Users/odinn/Projects/runes/nornir/`. `docs/plans/` is legacy planning history, not the live roadmap.

## Execution workflow

At the beginning of each implementation slice:

- capture or update the relevant Runes item first
- branch fresh from `main`
- load the `tdd` skill before writing any code
- use TDD for the current slice instead of writing the implementation in one lump

At the end of each implementation slice:

- load and run the `simplify` skill on changed code
- load and run the chosen reviewer skill on the diff
- run `./vendor/bin/pint`
- run `./vendor/bin/rector process`
- run `./vendor/bin/phpstan analyse`
- run `./vendor/bin/pest`
- commit the slice
- merge back to `main`
- stop for manual review and testing before starting the next slice

## Review

Another model - Codex, Claude or Gemini - will review your output once you are done.
