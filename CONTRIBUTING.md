# Contributing

Nornir is built around private archives and traceable evidence. That means the code can be public, but the habits need to be a little stricter than usual.

## Before You Start

Read these first:

- [README.md](README.md)
- [docs/nornir-spec.md](docs/nornir-spec.md)
- [docs/specifications/README.md](docs/specifications/README.md)
- [docs/handoff-explainer.md](docs/handoff-explainer.md)

The short version:

- importers normalize source-specific mess into canonical MySQL rows
- raw archives stay outside git
- generated markdown stays outside git
- source handoffs define bounded canonical slices
- Muninn and Huginn may interpret later, but importers do not

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Use a local MySQL database. Do not point Nornir at a database you are not willing to mutate.

## Safety Rules

Never commit:

- `.env`
- OAuth credentials
- OAuth tokens
- provider export zips
- extracted archives
- database dumps
- `data/`
- `wiki/`
- private screenshots or generated review bundles

If a fixture needs source data, make it synthetic and small. No "just one real message" nonsense. That way lies public embarrassment with a commit hash.

## Development Workflow

Use tight slices:

1. Update or add the relevant spec first.
2. Add a failing Pest test for the behavior.
3. Implement the smallest working change.
4. Run the focused test.
5. Simplify.
6. Run the full checks.

Default checks:

```bash
./vendor/bin/pint
./vendor/bin/rector process
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

Search rebuild, when relevant:

```bash
php artisan search:rebuild --dry-run
php artisan search:rebuild
```

## Code Style

- Follow Laravel conventions.
- Use Pest for tests.
- Prefer Eloquent models, relationships, scopes, actions, and focused query helpers over casual `DB::` usage.
- Keep importer-specific logic local to that importer.
- Share plumbing only when it is actually shared.
- Prefer `Import` over `Ingest` in new names.
- Prompt and skill assets belong in versioned files, not inline strings.

## Static Analysis Contracts

PHPStan shapes are part of the contract.

- Declare full array shapes for associative arrays returned from actions, DTOs, fixtures, and helpers.
- Use `@phpstan-type` for repeated shapes.
- Avoid `array<string, mixed>` unless the input is genuinely unknown external data.
- Initialize summary arrays with all known keys before mutating counts.
- If a test reads `$result->summary['foo']`, the result DTO must declare `foo`.

## Importer Rules

Every importer must:

- accept a bounded source
- record intake
- create or update a run
- preserve canonical source ids
- be idempotent across reruns
- avoid destructive deletion when later exports are thinner
- keep raw binary files out of canonical storage unless a spec explicitly says otherwise
- produce enough scope information for a source handoff

Importers must not:

- scrape by default
- wander outside the accepted source root
- infer biography or personality during import
- normalize everything into a fake universal source model
- become a giant switch statement with better lighting

## Tests

Use behavior names:

```php
it('imports_chatgpt_conversations_idempotently');
it('blocks_unbounded_wayback_imports');
it('preserves_facebook_message_timestamps_as_utc_instants');
```

Good tests use public interfaces: commands, actions, models, and documented behavior. Avoid testing private method choreography.

Architecture tests are mandatory for subsystem boundaries. If a change bends a boundary, update the spec and make the tradeoff explicit.

## Documentation

Docs are part of the product.

Update docs when you change:

- supported source files
- importer command signatures
- storage paths
- canonical tables
- handoff shapes
- provenance behavior
- setup requirements
- public/private source status

Do not leave specs in future tense after the code exists. Fossils are charming in museums, less so in repositories.

## Pull Requests

A good PR says:

- what changed
- why it changed
- which sources/modules/specs are affected
- what tests and checks ran
- whether storage, provenance, or boundary rules changed
- any known limitations

Keep PRs single-purpose. If the change is doing three unrelated things, split it before it grows opinions.
