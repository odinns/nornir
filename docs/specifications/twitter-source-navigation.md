# Twitter Source Navigation

## Start here

Primary input is a Twitter/X archive export.

For the current phase-1 slice, treat the archive at `data/` as the only relevant surface. The HTML renderer and bundled frontend assets are navigation sugar, not import input.

## Canonical source

- `data/manifest.js`
- allowed dataset files declared by the manifest and accepted by the importer
- archive-relative media directories referenced by accepted datasets

## Source layout or access model

The archive is a manifest-led bundle with two wrapper shapes:

- `window.__THAR_CONFIG = {...}` in `data/manifest.js`
- `window.YTD.<dataset>.part0 = [...]` in dataset files under `data/`

Phase 1 must support these files when present:

- required: `data/manifest.js`, `data/account.js`, `data/tweets.js`
- optional but supported: `data/profile.js`, `data/tweet-headers.js`, `data/community-tweet.js`, `data/note-tweet.js`, `data/screen-name-change.js`, `data/verified.js`, `data/verified-organization.js`
- optional media roots: `data/tweets_media/`, `data/community_tweet_media/`, `data/profile_media/`

The real archive also contains broader surfaces such as likes, follower/following snapshots, direct messages, Grok chat, Spaces, ads, contacts, Community Notes activity, Periscope data, and account-security telemetry. Those files exist, but phase 1 does not import them.

## Important entities

- archive identity from the manifest
- account identity from `account.js`
- profile snapshots from `profile.js` and related account-status files
- authored tweets from `tweets.js`
- authored community tweets from `community-tweet.js`
- authored note tweets from `note-tweet.js`
- archive-relative media references attached to accepted datasets
- screen-name history from `screen-name-change.js`

## Traversal rules

- start from `data/manifest.js`, then open only importer-allowed files
- treat manifest presence as validation input, not as permission to ingest every non-zero dataset
- keep wrapper globals distinct from normalized payloads
- treat `tweet-headers.js` as a supporting index for validation and completeness checks, not as a second tweet body source
- resolve media paths only relative to the accepted archive root

## Safe access rules

- treat archive as immutable
- do not read outside the chosen archive root
- avoid attachment-heavy traversal unless a supported dataset references media
- do not widen scope because the archive happens to contain more categories than phase 1 needs

## Parser notes

- preserve stable source ids and original timestamp strings
- `tweets.js` and `community-tweet.js` use Twitter-style `created_at` strings such as `Tue Feb 17 06:30:43 +0000 2026`
- `note-tweet.js`, `screen-name-change.js`, and manifest metadata use ISO timestamps
- canonical database datetimes must be stored as UTC instants
- distinguish authored tweet surfaces from non-authored or weakly-timestamped activity
- likes and follower/following exports are real datasets, but they lack chronology good enough for phase-1 timeline import

## Bottom line

The archive is manifest-led and much broader than the phase-1 importer. The right move is explicit allowlisting: import the small set of biography-relevant authored and profile datasets, and document the rest as intentionally deferred.
