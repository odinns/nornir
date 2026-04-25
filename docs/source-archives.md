# Source Archives

This guide explains how to obtain source material for Nornir.

Provider settings move. The links below were checked on 2026-04-25, but account menus are not sacred texts. If a label moved, search the provider settings for "download your data", "export your information", or "privacy request".

## General Rules

- Prefer machine-readable exports: JSON for Meta/ChatGPT/X, CSV for LinkedIn, XML for Apple Health.
- Request "all time" unless you are intentionally importing a bounded slice.
- Keep the original zip files somewhere safe.
- Extract into `data/sources/<source>/...` or another ignored local path.
- Do not commit raw exports, extracted archives, credentials, tokens, or generated review bundles.
- Spot-check the export before importing. Providers sometimes return partial archives with a straight face.

## Facebook

Official docs:

- https://www.facebook.com/help/212802592074644
- https://www.facebook.com/help/1701730696756992/

Recommended export:

- Format: JSON
- Date range: all time, unless deliberately scoped
- Media quality: high when attachment references matter
- Categories: profile information, friends/connections, messages, posts, comments/reactions, media references

Typical path:

1. Open Meta Accounts Center.
2. Go to **Your information and permissions**.
3. Choose **Download your information** or **Export your information**.
4. Select the Facebook profile.
5. Choose **Download to device**.
6. Choose JSON, all time, and the categories above.
7. Download the zip when Meta says it is ready.
8. Extract it under `data/sources/facebook/`.

Import:

```bash
php artisan import:facebook data/sources/facebook/<extracted-export-root>
```

## Instagram

Instagram downloads now run through Meta Accounts Center.

Official/current Meta entry points:

- https://www.facebook.com/help/836165424150596/
- https://about.fb.com/news/2023/10/manage-your-information-across-apps/

Recommended export:

- Format: JSON
- Date range: all time
- Media quality: high
- Categories: personal information and Instagram activity/media

Current importer scope:

- profile/account metadata
- posts
- profile photo references
- optional stories
- media references

Deferred surfaces:

- DMs
- likes
- comments
- followers/following
- ads
- login/security telemetry

Import:

```bash
php artisan import:instagram data/sources/instagram/<extracted-export-root>
```

## X / Twitter

Official docs:

- https://help.x.com/managing-your-account/how-to-download-your-twitter-archive
- https://twitter.com/settings/download_your_data

Recommended export:

- Request the full X archive.
- Wait for the email or push notification.
- Download while logged into the same account/browser.
- Extract the zip and keep the archive structure intact.

Current importer scope:

- account/profile snapshots
- authored tweets
- note tweets
- community tweets
- screen-name changes
- media references attached to supported datasets

Deferred surfaces:

- DMs
- likes
- followers/following
- ads
- contacts
- Grok/chat or account telemetry

Import:

```bash
php artisan import:twitter data/sources/twitter/<extracted-archive-root>
```

## LinkedIn

Official docs:

- https://www.linkedin.com/help/linkedin/answer/a1339364/download-your-account-data
- https://www.linkedin.com/help/linkedin/answer/a1339364/downloading-your-account-data

Recommended export:

- Use **Settings & Privacy**.
- Open **Data Privacy**.
- Choose **Get a copy of your data**.
- Request the larger archive, not just one tiny category.
- Download promptly. LinkedIn download links expire.

Current importer scope:

- profile snapshot
- positions, education, projects
- skills and languages
- recommendations and endorsements
- connections and invitations
- shares, comments, reactions, rich media
- messages and message attachment URLs as references

Import:

```bash
php artisan import:linkedin data/sources/linkedin/<extracted-export-root>
```

## ChatGPT

Official docs:

- https://help.openai.com/en/articles/7260999-how-do-i-export-my-chatgpt-history-and-data
- https://privacy.openai.com/

Recommended export:

- Use ChatGPT Settings > Data Controls > Export, or the OpenAI Privacy Portal.
- Download the zip from the email before the link expires.
- Extract it under `data/sources/chatgpt/`.
- Preserve `conversations.json` or chunked `conversations-*.json` files.

Import:

```bash
php artisan import:chatgpt data/sources/chatgpt/<extracted-export-root>
```

If you keep multiple exports:

```bash
php artisan import:chatgpt data/sources/chatgpt --glob="*/conversations*.json"
```

## Gmail

Nornir's implemented Gmail path uses the Gmail API, not Google Takeout MBOX.

Use [docs/gmail-access.md](gmail-access.md) for the OAuth setup.

Google Takeout is still useful for preservation:

- https://support.google.com/accounts/answer/3024190

Takeout exports Gmail as MBOX. That is a good independent backup, but MBOX import is not the current first-class Nornir path.

## Apple Messages

Current importer source:

- `~/Library/Messages/chat.db`
- optional attachment root: `~/Library/Messages/Attachments`
- optional Contacts/AddressBook sqlite databases for name enrichment

Practical notes:

- macOS protects Messages data. Use a terminal/app with Full Disk Access.
- Prefer copying from a backup or making a read-only copy before importing.
- Do not import directly from a live database while Messages is actively writing to it if you can avoid it.

Example:

```bash
mkdir -p data/sources/apple-messages
cp ~/Library/Messages/chat.db data/sources/apple-messages/chat.db
php artisan import:apple-messages data/sources/apple-messages/chat.db --attachments-root=~/Library/Messages/Attachments
```

## Apple Health

Official Apple privacy/data docs:

- https://support.apple.com/102283
- https://privacy.apple.com/

The Health app can also export directly from the device:

1. Open Health on iPhone.
2. Tap your profile picture.
3. Choose **Export All Health Data**.
4. Save the zip.
5. Extract it and import `export.xml` or `eksport.xml`.

Import:

```bash
php artisan import:apple-health data/sources/apple-health/export.xml
```

## Wayback Machine

Wayback is useful when a public site, portfolio, project page, blog, or company page is part of the biography trail.

Nornir uses a bounded host/prefix/exact URL query. Keep it narrow.

Dry-run first:

```bash
php artisan import:wayback example.com --match=host --from=20040101 --to=20201231 --dry-run --list-snapshots
```

Then import:

```bash
php artisan import:wayback example.com --match=host --from=20040101 --to=20201231 --limit=250
```

## Media Collection / Monique

`import:media-collection` reads a mostly-unique/Monique MySQL database. That companion project is not public yet.

For public users, treat this importer as a pattern:

- external database remains canonical
- Nornir reads it through a bounded read-only bridge
- Nornir stores photo/video metadata and references, not binary copies

Import when you have the private companion database:

```bash
php artisan import:media-collection /path/to/monique/.env --volume=LIMA-2 --path-prefix=/Volumes/LIMA-2/Pictures
```

## FidoNet

`import:fidonet` reads an unpublished GoldED/FidoNet database. It does not import from generic packet/mailbox archives yet.

For public users, treat it as another external-database bridge:

- preserve the external canonical database boundary
- import Nornir-owned derived/helper state
- keep area include/exclude scopes explicit

Import when you have the private companion database:

```bash
php artisan import:fidonet /path/to/golded/.env --area=AREA.CODE
```

## Other Sources

Useful source material that does not have first-class importers yet can still live under `data/sources/` for manual evidence work:

- CVs and resumes
- personality tests
- public articles and PDFs
- project exports
- website snapshots
- local photo/document indexes
- chat exports from other providers

Do not force them through a fake generic importer. Put them in a bounded source folder, document what they are, and build a real importer when the shape proves worth preserving.
