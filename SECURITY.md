# Security

Nornir handles personal archives, correspondence, health data, photos, account exports, and OAuth tokens. Treat every local checkout as sensitive.

## Supported Use

Use Nornir for:

- your own archives
- archives you have explicit permission to process
- public material fetched through bounded, respectful access
- local research where raw data remains under your control

Do not use Nornir to bypass access controls, scrape private accounts, or process someone else's private archive without consent.

## Secrets And Private Data

Never commit:

- `.env`
- Gmail `credentials.json`
- Gmail `token.json`
- provider export zips
- extracted archives
- database dumps
- `data/`
- `wiki/`
- generated review bundles containing real source material

Before making a fork public:

```bash
git status --short --ignored
git ls-files
```

Then inspect any docs or fixtures that may contain real personal content.

## Reporting Security Issues

If you find a security issue, open a private report or contact the maintainer directly. Do not publish a proof-of-concept with real personal data.

Useful report details:

- affected command or source
- exact boundary bypass or leakage
- whether raw data, credentials, tokens, or generated artifacts can be exposed
- minimal synthetic reproduction

## Data Boundary Principle

Nornir should fail closed around source boundaries:

- local archive importers must stay inside accepted roots
- API importers must record query/scope
- external database bridges must be read-only
- generated claims must be traceable to imported evidence

When in doubt, preserve less and explain more.
