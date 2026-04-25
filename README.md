# Nornir

Nornir is a Laravel-based system for ingesting, processing, understanding, and presenting personal source material.

The repository starts backend-first:

- Laravel scaffold
- quality gates
- architecture tests
- importer and pipeline work in later roadmap slices

Read the specs before inventing policy:

- `docs/nornir-spec.md`
- `docs/specifications/README.md`
- `docs/handoff-explainer.md` if the importer handoff layer still feels like wizard fog

## Development

- `composer test`
- `./vendor/bin/pint`
- `./vendor/bin/rector process`
- `./vendor/bin/phpstan analyse`

Local search uses Scout with Meilisearch:

- `meilisearch --db-path data/meilisearch --master-key local-dev-key`
- `php artisan search:rebuild`

Operational state stays out of git:

- `data/`
- `wiki/`

## Roadmap

The bootstrap work is complete.

Active roadmap and backlog work live in `/Users/odinn/Projects/runes/nornir/`.

`docs/plans/` is legacy planning history. Keep it for context, not as the live source of truth.

New work should be captured as atomic Runes items:

- `bugs/` for broken behavior
- `features/` for new capability
- `tasks/` for implementation, cleanup, and refactors
- `ideas/` for things worth saving before they become work
