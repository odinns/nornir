# Apple Messages Source Navigation

## Start here

Primary input is a local Apple Messages `chat.db` database or equivalent structured export with stable message ids.

## Canonical source

- Apple Messages `chat.db` database or equivalent structured export

## Source layout or access model

- message rows
- contacts or participants lookup
- optional attachments

## Important entities

- messages
- conversations
- participants
- attachments

## Traversal rules

- preserve source ids and timestamp fields
- normalize conversation grouping as derived where necessary

## Safe access rules

- treat source database or export as immutable

## Parser notes

- contact name resolution is derived convenience, not canonical truth

## Bottom line

Apple Messages is message-first with derived conversation grouping.
