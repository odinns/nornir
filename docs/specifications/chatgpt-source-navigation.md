# ChatGPT Source Navigation

## Start here

Primary inputs are ChatGPT export archives or approved API-backed export equivalents.

## Canonical source

- local export archive contents
- conversation JSON chunks are primary truth

## Source layout or access model

- conversation JSON files
- HTML export wrapper
- optional asset directories per conversation

## Important entities

- conversations
- nodes
- messages
- message parts
- assets

## Traversal rules

- traverse chunked conversation JSON, not the HTML wrapper first
- preserve graph structure before deriving visible transcripts

## Safe access rules

- treat the export as immutable
- do not assume all assets resolve locally

## Parser notes

- distinguish author roles
- preserve parent and child links
- keep visible transcript derivation as derived data

## Bottom line

This source is conversation-graph-first, not flat transcript-first.
