# Instagram Source Navigation

## Start here

This is a planned optional source, expected to use export archives first and official API access where appropriate and permitted.

The current grounded source shape is a downloadable Meta archive rooted like:

- `instagram-<account>-<export-date>-<suffix>/`
- category folders such as `personal_information/`, `your_instagram_activity/`, `connections/`, `media/`, and `security_and_login_information/`

## Canonical source

- downloadable archive when available
- official API responses when stable and allowed

## Source layout or access model

- archive JSON files with inconsistent top-level shapes
- profile metadata under `personal_information/`
- post and story metadata under `your_instagram_activity/media/`
- archive-relative media binaries under `media/`
- additional secondary surfaces such as messages, comments, likes, followers, and login history

## Important entities

- account profile snapshot from `personal_information/personal_information/personal_information.json`
- posts from `your_instagram_activity/media/posts_1.json`
- profile photo refs from `your_instagram_activity/media/profile_photos.json`
- optional stories from `your_instagram_activity/media/stories.json`
- captions carried in media item `title` fields when present
- timestamps from source-specific fields such as `creation_timestamp`
- media references resolved from archive-relative `uri` values
- messages as a later-phase source surface, not part of the phase-1 importer slice

## Traversal rules

- prefer bounded archive import
- bind traversal to the accepted archive root only
- traverse phase-1 files explicitly rather than discovering every JSON file generically
- resolve media only through archive-relative paths referenced by accepted JSON payloads
- API use must be explicitly scoped and supported

## Safe access rules

- no scraping in the base design
- low-volume data is still valid input
- do not treat the presence of messages, followers, or ad data as justification to widen phase 1
- do not assume every export vintage contains the same files or top-level keys

## Parser notes

- archive and API availability may change
- design the importer to degrade gracefully when the source surface is sparse
- top-level payloads vary by file: some files are arrays, others are objects with one payload key
- nested value maps such as `string_map_data`, `media_map_data`, `string_list_data`, and `label_values` are normal in the archive
- some exported text is mojibaked and may need decoding or normalization downstream
- `media/` binaries are separate from JSON payloads and must be linked by reference, not inferred by filename guessing

## Bottom line

Instagram is planned, optional, and bounded by supported access modes.

For phase 1, the honest bounded archive slice is profile/account metadata, posts, profile photo refs, and optional stories. Messages, followers, likes, comments, login history, and ads exist in the archive but are intentionally deferred.
