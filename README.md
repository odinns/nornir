# Nornir

Nornir is a Laravel-based system for ingesting, processing, understanding, and presenting personal source material.

The repository starts backend-first:

- Laravel scaffold
- quality gates
- architecture tests
- importer and pipeline work in later phases

Read the specs before inventing policy:

- `docs/nornir-spec.md`
- `docs/specifications/README.md`

## Development

- `composer test`
- `./vendor/bin/pint`
- `./vendor/bin/rector process`
- `./vendor/bin/phpstan analyse -vvv`

## Phase workflow

At the beginning of each implementation phase:

- branch fresh from `main`
- freshly load the `tdd`, `simplify`, and chosen reviewer skill before doing work
- use TDD for the phase slice instead of writing the implementation in one lump

At the end of each phase:

- run `./vendor/bin/pint`
- run `./vendor/bin/rector process`
- run `./vendor/bin/phpstan analyse -vvv`
- run `./vendor/bin/pest`
- commit the phase
- merge back to `main`
- stop for manual review and testing before starting the next phase

Operational state stays out of git:

- `data/`
- `wiki/`
