# Facebook Source Navigation

## Start here

Primary input is a Facebook export archive, especially Messenger message datasets.

## Canonical source

- archive JSON files
- message inbox trees

## Source layout or access model

- per-thread directories
- JSON message files
- optional attachments and media references

## Important entities

- threads
- participants
- messages
- reactions
- attachments

## Traversal rules

- walk thread manifests first
- preserve thread identity and source-relative paths

## Safe access rules

- do not bulk-read attachments unless explicitly needed
- treat archive files as immutable

## Parser notes

- normalize participant naming carefully
- keep attachment metadata separate from binary ownership

## Bottom line

The archive is thread-oriented and should stay that way in canonical storage.
