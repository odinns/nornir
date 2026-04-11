# Twitter To Nornir Importer

## Goal

Import the biography-relevant Twitter/X archive slices into canonical MySQL tables for public-expression timeline and profile history.

## Canonical source

Twitter/X export archive datasets under an approved archive root, validated against `data/manifest.js`.

## Inputs

- archive path
- optional validate-only or dry-run flags

For the current phase-1 slice, the importer should expect and use these paths when present:

- required: `data/manifest.js`, `data/account.js`, `data/tweets.js`
- optional but supported: `data/profile.js`, `data/tweet-headers.js`, `data/community-tweet.js`, `data/note-tweet.js`, `data/screen-name-change.js`, `data/verified.js`, `data/verified-organization.js`
- optional media roots: `data/tweets_media/`, `data/community_tweet_media/`, `data/profile_media/`

## Output structure

- canonical `twitter_*` tables
- run artifacts under `data/imports/twitter/`

## MySQL storage model

- `twitter_archives`
- `twitter_accounts`
- `twitter_profile_snapshots`
- `twitter_screen_name_changes`
- `twitter_tweets`
- `twitter_note_tweets`
- `twitter_media_refs`

## Data model

Store only entities that are actually supported by the phase-1 archive slice.

Phase 1 must preserve:

- archive identity and generation metadata
- imported account identity
- profile snapshot fields and account-status snapshot fields when present
- screen-name change history
- authored tweet identity, body text, source client, language, reply linkage, and observed engagement counters
- note-tweet identity and body text from its distinct archive shape
- archive-relative media references for accepted datasets only

`twitter_tweets` must hold both standard tweets and community tweets, with an explicit source-surface discriminator rather than separate fake domains.

`tweet-headers.js` is supporting input only. It may help validate counts or missing tweet bodies, but it does not replace `tweets.js` as the canonical tweet-content source.

## Import rules

- manifest-first validation, allowlist-first import
- use file-specific extractors for known datasets instead of a generic "parse every file in data/" pass
- parse the archive JS wrappers explicitly and fail with source-path context when a supported file has the wrong shape
- import normal tweets from `tweets.js`
- import community tweets from `community-tweet.js` into the same canonical tweet surface, marked as community-scoped
- import note tweets from `note-tweet.js` into `twitter_note_tweets`
- import account/profile/status snapshot data from the supported account files when present
- import screen-name history from `screen-name-change.js`
- keep media as referenced metadata; store archive-relative paths and source metadata without copying binaries
- ignore unsupported archive categories deliberately, even when present and non-empty
- treat likes, follower/following graph, DMs, Grok chat, Spaces, ads, contacts, Periscope, Community Note ratings, and account-security telemetry as deferred phase-1 exclusions

## Incremental behavior

- rerun by archive identity plus stable source identity
- tweets and note tweets must upsert idempotently by stable archive ids, not by import-run order
- later archives may omit optional datasets or older history without implying canonical deletion
- snapshot-style rows should tolerate sparse reruns without manufacturing fake completeness

## Validation

- validate the accepted archive root shape before import
- validate required file presence for full phase-1 import
- allow optional supported files to be absent without failing the run
- validate wrapper shape for every supported file that is present
- validate tweet-body counts against `tweet-headers.js` when both files are present
- validate reply linkage and conversation linkage where the dataset provides it
- validate archive-relative media references and report missing files clearly
- preserve original source timestamp strings and normalize canonical datetimes to UTC without local-time drift

## Wiki compilation handoff

- source pages and timeline evidence compile from canonical tweet, note-tweet, and profile rows only
- likes, social graph, and deferred archive datasets must not leak into the handoff during phase 1

## Forbidden behavior

- no scraping-first implementation
- no collapsing profile snapshots into one fake timeless profile
- no `twitter_interactions` junk drawer table for unrelated archive categories
- no treating likes or follow graph as timeline events without real chronology
- no DM, Grok, Spaces, Periscope, or ad-history importer smuggled into phase 1
- no binary copying into canonical storage

## Review checklist

- accepted phase-1 files are named explicitly
- deferred archive surfaces are named explicitly
- `tweets.js` remains the canonical tweet-content source
- public-expression chronology survives normalization
- additive reruns do not depend on archive completeness
- media stays reference-only

## Acceptance checks

- the importer can be implemented from this spec without inventing source policy
- authored tweet, community tweet, note-tweet, and profile-history evidence is queryable from MySQL
- likes, follow graph, and other deferred archive surfaces are not accidentally part of phase 1
