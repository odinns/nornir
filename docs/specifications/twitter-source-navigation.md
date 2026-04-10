# Twitter Source Navigation

## Start here

Primary input is a Twitter/X archive export.

## Canonical source

- archive manifest
- tweet payload datasets
- account and interaction datasets

## Source layout or access model

- archive manifest
- tweets
- likes
- profile and account metadata
- media references

## Important entities

- tweets
- replies
- media references
- profile snapshots

## Traversal rules

- start from archive manifest and declared dataset files
- keep source wrappers distinct from normalized content

## Safe access rules

- treat archive as immutable
- avoid attachment-heavy traversal unless needed

## Parser notes

- preserve tweet ids and timestamps
- distinguish authored tweets from interactions where available

## Bottom line

The archive is dataset-manifest-first, not "just parse random JSON files".
