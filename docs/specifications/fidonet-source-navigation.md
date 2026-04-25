# FidoNet Source Navigation

## Start here

Primary input is the existing canonical GoldED/FidoNet database rather than a pretend archive. That companion source is not public yet.

## Canonical source

- established FidoNet database

## Source layout or access model

- messages
- areas
- threading-related keys
- author and routing fields

## Important entities

- messages
- areas
- participants
- derived cleanup and thread views

## Traversal rules

- read canonical message rows first
- derive cleanup and threads separately

## Safe access rules

- respect the canonical database boundary
- do not duplicate canonical rows just to make the architecture look symmetrical

## Parser notes

- body cleanup is derived
- thread assembly is derived

## Bottom line

This source is database-first and should stay database-first.
