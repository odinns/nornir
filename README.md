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

Operational state stays out of git:

- `data/`
- `wiki/`
