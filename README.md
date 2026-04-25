# Nornir

Nornir is a Laravel application for importing personal archives into a local, queryable evidence store.

It is built for biography work: reconstructing timelines, correspondence, relationships, projects, habits, places, photos, public writing, and the awkward little details that disappear when your life is scattered across export zips and old databases.

This is not a hosted service. It is local-first research software. Your archives stay on your machine, canonical imported rows live in MySQL, generated/review material lives under ignored local paths, and the repository should contain code and docs only.

## Current State

Nornir already has a working backend slice:

- Laravel 13 app on PHP 8.4
- MySQL-backed canonical tables for imported sources
- intake records, import runs, run artifacts, and provenance links
- source-specific importer commands
- bounded handoff builders for post-import evidence work
- Scout/Meilisearch search projection across imported material
- Gmail API authentication, import, plaintext backfill, and important-mail triage
- Pest feature/unit/architecture tests
- PHPStan, Pint, and Rector quality gates

There is no real Mimir web UI yet. The useful surface today is CLI import, canonical database inspection, search indexing, and source handoffs. That is enough to start serious biographical digging without pretending the palace has wallpaper.

## What It Can Import

| Source | Command | Status |
| --- | --- | --- |
| ChatGPT exports | `php artisan import:chatgpt <path>` | Working archive importer |
| Facebook exports | `php artisan import:facebook <path>` | Working archive importer |
| X/Twitter exports | `php artisan import:twitter <path>` | Working archive importer |
| LinkedIn exports | `php artisan import:linkedin <path>` | Working archive importer |
| Instagram exports | `php artisan import:instagram <path>` | Working archive importer for profile/posts/media refs |
| Gmail API | `php artisan import:gmail <credentials.json> --query="..."` | Working bounded API importer |
| Apple Messages | `php artisan import:apple-messages <chat.db-or-dir>` | Working local database importer |
| Apple Health | `php artisan import:apple-health <export.xml-or-dir>` | Working XML importer |
| Wayback Machine | `php artisan import:wayback <host-or-url>` | Working bounded CDX importer |
| Media collection | `php artisan import:media-collection <env-file>` | Works with an unpublished Monique/mostly-unique database |
| FidoNet | `php artisan import:fidonet <env-file>` | Works with an unpublished GoldED/FidoNet database |

Monique/media-collection and FidoNet support are real in this codebase, but they currently depend on private companion projects and database schemas. Public users should treat those as examples of external-database bridges until the upstream projects are published.

## What You Can Do With It Today

Nornir is already useful for private evidence work:

- Import archives into source-specific canonical tables instead of rummaging through raw JSON and CSV every time.
- Rerun imports without treating later thinner exports as deletion events.
- Preserve provenance: source paths, run records, source ids, timestamps, and attachment references.
- Build bounded handoffs from canonical rows so later biography tooling can work from a known slice.
- Rebuild search documents and inspect cross-source matches with Meilisearch.
- Use Gmail query scopes to isolate eras, correspondents, projects, or messy little correspondence knots.
- Compare signals across sources: a LinkedIn role, Gmail thread, Facebook message, photo directory, ChatGPT conversation, and Wayback page can all become evidence in the same local system.

The Muninn biography pipeline and Huginn personality pipeline are specified but still early. The current practical workflow is: import sources, rebuild search, inspect canonical rows and handoffs, then write or generate evidence notes with provenance intact.

## Setup

Requirements:

- PHP 8.4
- Composer
- Node.js/npm
- MySQL
- Meilisearch if you want local search

Install:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`, then migrate:

```bash
php artisan migrate
```

For local search:

```bash
meilisearch --db-path data/meilisearch --master-key local-dev-key
php artisan search:rebuild
```

Run the checks:

```bash
./vendor/bin/pint
./vendor/bin/rector process
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

## Where To Put Source Material

Raw archives do not belong in git.

Use ignored local paths:

```text
data/sources/facebook/
data/sources/instagram/
data/sources/twitter/
data/sources/linkedin/
data/sources/chatgpt/
data/sources/apple-messages/
data/sources/apple-health/
data/sources/gmail/
```

`data/` and `wiki/` are ignored. Keep credentials, tokens, export zips, extracted archives, generated review bundles, and compiled notes out of commits.

## Import Examples

```bash
php artisan import:chatgpt data/sources/chatgpt/export
php artisan import:facebook data/sources/facebook/facebook-yourname-json
php artisan import:twitter data/sources/twitter/twitter-archive
php artisan import:linkedin data/sources/linkedin/Basic_LinkedInDataExport
php artisan import:instagram data/sources/instagram/instagram-yourname-json
php artisan import:apple-health data/sources/apple-health/export.xml
php artisan import:apple-messages ~/Library/Messages/chat.db --attachments-root=~/Library/Messages/Attachments
```

Gmail is API-first:

```bash
php artisan gmail:auth data/sources/gmail/credentials.json
php artisan import:gmail data/sources/gmail/credentials.json --query="from:someone@example.com OR to:someone@example.com"
php artisan gmail:backfill-body-plain
NORNIR_GMAIL_CREDENTIALS=data/sources/gmail/credentials.json php artisan gmail:triage-important --window="last 14 days"
```

Wayback imports are bounded by host, prefix, or exact URL:

```bash
php artisan import:wayback example.com --match=host --from=20040101 --to=20201231 --limit=250
php artisan import:wayback https://example.com/about --match=exact --dry-run --list-snapshots
```

## Getting Archives

See [docs/source-archives.md](docs/source-archives.md) for current export instructions and official provider links.

Short version:

- Meta/Facebook/Instagram: use Accounts Center, request JSON, all time, high media quality when you care about references.
- X/Twitter: request your X archive from account settings and import the extracted archive root.
- LinkedIn: request the larger data archive from Settings & Privacy.
- ChatGPT: export from ChatGPT Data Controls or OpenAI Privacy Portal.
- Gmail: use the Gmail API flow in [docs/gmail-access.md](docs/gmail-access.md). Google Takeout MBOX is useful for preservation, but the implemented importer is API-first.
- Apple Messages: copy `chat.db` and attachments from your Mac carefully, preferably from a backup or full-disk-access shell.
- Apple Health: export from the Health app and import `export.xml`/`eksport.xml`.

## Documentation Map

- [docs/nornir-spec.md](docs/nornir-spec.md): system-level architecture.
- [docs/specifications/README.md](docs/specifications/README.md): implementation specs and read order.
- [docs/source-archives.md](docs/source-archives.md): how to obtain source archives.
- [docs/gmail-access.md](docs/gmail-access.md): Gmail OAuth setup and import flow.
- [docs/handoff-explainer.md](docs/handoff-explainer.md): what source handoffs are and why they exist.

## Safety

This repo is designed around intensely private data. Before making it public:

- run `git status --ignored --short` and check that archives stay ignored
- never commit `.env`, OAuth credentials, `token.json`, Takeout archives, extracted provider exports, database dumps, `data/`, or `wiki/`
- keep private working notes under ignored `data/`, not under tracked `docs/`
- treat local MySQL data as sensitive, even when the app environment says `local`

Nornir is a tool for your own data and archives you have the right to process. Do not use it to scrape, bypass access controls, or import other people’s private archives without consent. That way lies both legal trouble and bad taste.

## License

MIT. See [LICENSE](LICENSE).
