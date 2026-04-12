# LinkedIn Source Navigation

## Start here

Primary input is a LinkedIn data export directory full of CSV files, not a neat manifest-led archive.

For phase 1, treat the export root itself as the only supported source boundary. Do not widen scope just because the export contains more account exhaust than biography value.

## Canonical source

- CSV files in the chosen export root
- only the importer allowlist for biography and timeline use

## Source layout or access model

The export is CSV-first and flat at the root, with a small number of nested folders such as `Jobs/` and `Verifications/`.

Phase 1 must support these files when present:

- `Profile.csv`
- `Email Addresses.csv`
- `PhoneNumbers.csv`
- `Whatsapp Phone Numbers.csv`
- `Registration.csv`
- `Profile Summary.csv` as optional and usually empty
- `Positions.csv`
- `Education.csv`
- `Projects.csv`
- `Skills.csv`
- `Languages.csv`
- `Recommendations_Received.csv`
- `Recommendations_Given.csv`
- `Endorsement_Received_Info.csv`
- `Endorsement_Given_Info.csv`
- `Connections.csv`
- `Invitations.csv`
- `Shares.csv`
- `Comments.csv`
- `Reactions.csv`
- `Rich_Media.csv`
- `messages.csv`

The real export also contains broader surfaces such as jobs, learning, search history, ads, targeting, security logs, receipts, imported contacts, follow data, votes, verifications, and system-generated coach or guide messages. Those files exist, but phase 1 does not import them.

## Important entities

- archive identity from the chosen export root
- profile snapshot for the archive owner
- dated career-history rows: positions, education, projects
- biography evidence: skills, languages, recommendations, endorsements
- network evidence: connections and invitations
- authored/public activity: shares, comments, reactions, rich media
- private human conversations and messages from `messages.csv`
- remote message attachment URLs as references only

## Traversal rules

- start from the chosen export root and open only importer-allowed files
- use file-specific parsers instead of a generic “import every CSV” loop
- treat `Connections.csv` as a special case with a preamble before the real header
- keep `messages.csv` conversation identity explicit through `CONVERSATION ID`
- keep `Rich_Media.csv` as its own evidence surface unless a deterministic join to shares exists

## Safe access rules

- treat the export as immutable
- do not read outside the chosen export root
- no scraping and no API fallback in phase 1
- do not widen scope into jobs, ads, search, security, or account telemetry
- do not treat remote LinkedIn attachment URLs as local archive files

## Parser notes

- timestamp formats differ across files and must be parsed per-file
- `messages.csv` has conversation ids but no message ids, so message identity must be derived deterministically from row content
- `Connections.csv` begins with a note block, an empty line, then the actual header row
- some text in real exports may contain mojibake or replacement-character damage
- canonical database datetimes must be written as UTC instants

## Bottom line

This phase is biography-first, not LinkedIn-completion-first. Import the slices that help reconstruct personal history and timeline, and leave the telemetry sludge where it belongs.
