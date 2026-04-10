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
- one approved local root path or an explicit bounded list of local root paths

## Important entities

- conversations
- nodes
- messages
- message parts
- assets

## Traversal rules

- traverse chunked conversation JSON, not the HTML wrapper first
- preserve graph structure before deriving visible transcripts
- when multiple roots are configured, traversal stays inside that allowlisted set only

## Safe access rules

- treat the export as immutable
- do not assume all assets resolve locally
- direct read access from external local roots is allowed
- do not copy the export into Nornir-managed storage unless a separate review or portability need justifies it

## Parser notes

- distinguish author roles
- preserve parent and child links
- keep visible transcript derivation as derived data

## Bottom line

This source is conversation-graph-first, not flat transcript-first.
