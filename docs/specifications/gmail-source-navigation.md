# Gmail Source Navigation

## Start here

Primary input is Gmail via official API access or approved export data.

## Canonical source

- Gmail API message and thread data
- export archive only when API is unavailable or intentionally replaced

## Source layout or access model

- mailbox query scope
- threads
- messages
- labels
- attachments metadata

## Important entities

- accounts
- threads
- messages
- labels
- attachment references

## Traversal rules

- import by explicit query or history scope
- preserve Gmail ids and label state

## Safe access rules

- bounded query only
- explicit account configuration

## Parser notes

- preserve headers and normalized text separately
- keep attachment metadata and optional references distinct

## Bottom line

Gmail is API-first unless a specific archive flow is chosen.
