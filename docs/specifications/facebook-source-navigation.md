# Facebook Source Navigation

## Start here

Primary input is a Facebook export archive with biography-relevant slices spread across a few major roots, not one tidy manifest.

## Canonical source

- `personal_information/`
- `connections/`
- `your_facebook_activity/messages/`
- `your_facebook_activity/posts/`
- `your_facebook_activity/comments_and_reactions/`

## Source layout or access model

- category-first archive tree
- per-thread Messenger directories with `message_*.json` chunks
- JSON category exports for profile, social graph, posts, comments, and reactions
- optional attachment and media references that stay reference-only in canonical storage

## Important entities

- profile snapshot
- people
- social edges
- threads
- participants
- messages
- posts
- comments
- reactions
- attachments

## Traversal rules

- traverse only approved biography-facing categories
- preserve source-relative paths for message and post media refs
- keep Messenger thread identity explicit even when history is split across multiple chunks

## Safe access rules

- do not wander into security, ads, preferences, or logged telemetry during this phase
- do not bulk-read attachments beyond light metadata checks
- treat archive files as immutable

## Parser notes

- normalize Facebook mojibake before canonical persistence
- normalize participant naming carefully
- keep social-graph edge types explicit
- keep attachment metadata separate from binary ownership

## Bottom line

This phase is biography-first: Messenger, profile, social graph, posts, comments, reactions, and attachment refs. The rest can wait.
