<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\InstagramImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ImportInstagramArchiveAction
{
    private const string TABLE_ACCOUNTS = 'instagram_accounts';

    private const string TABLE_PROFILE_SNAPSHOTS = 'instagram_profile_snapshots';

    private const string TABLE_POSTS = 'instagram_posts';

    private const string TABLE_MEDIA_REFS = 'instagram_media_refs';

    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly SourceObservationStore $sourceObservationStore,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): InstagramImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'instagram-import',
            import: fn (Run $run): array => $this->importArchive($dispatchPayload, $run, $progress),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->importArtifactWriter->write($run, $dispatchPayload, 'instagram', 'instagram-import-summary', $summary);
            },
        );

        /** @var array{run: Run, summary: array{username:string, posts:int, inserted_posts:int, reobserved_posts:int, media_refs:int, profile_photos:int, stories:int, stories_skipped:bool}} $execution */
        return new InstagramImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array{username:string, posts:int, inserted_posts:int, reobserved_posts:int, media_refs:int, profile_photos:int, stories:int, stories_skipped:bool}
     */
    private function importArchive(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        $archiveRoot = $this->resolveArchiveRoot($dispatchPayload);
        $this->validateArchiveRoot($archiveRoot);

        $personalInfoPath = $archiveRoot.'/personal_information/personal_information/personal_information.json';
        $profileUser = $this->readJson($personalInfoPath)['profile_user'][0] ?? [];

        [$accountId, $accountKey, $username] = $this->importAccount($dispatchPayload->accessMode, $profileUser);
        $this->importProfileSnapshot($accountId, $accountKey, $profileUser, $run);
        $postSummary = $this->importPosts($archiveRoot, $accountId, $accountKey, $run, $progress);
        $mediaRefs = $postSummary['media_refs'];
        $profilePhotos = $this->importProfilePhotos($archiveRoot, $accountId, $accountKey, $run);
        $mediaRefs += $profilePhotos;

        $storiesResult = $this->importStories($archiveRoot, $accountId, $accountKey, $run);
        $mediaRefs += $storiesResult['count'];

        return [
            'username' => $username,
            'posts' => $postSummary['posts'],
            'inserted_posts' => $postSummary['inserted_posts'],
            'reobserved_posts' => $postSummary['reobserved_posts'],
            'media_refs' => $mediaRefs,
            'profile_photos' => $profilePhotos,
            'stories' => $storiesResult['count'],
            'stories_skipped' => $storiesResult['skipped'],
        ];
    }

    private function resolveArchiveRoot(ImporterDispatchData $dispatchPayload): string
    {
        if ($dispatchPayload->accessMode !== 'local-path') {
            throw new InvalidArgumentException('Instagram imports require a local-path archive directory.');
        }

        return $dispatchPayload->sourceLocator;
    }

    private function validateArchiveRoot(string $root): void
    {
        if (! File::isDirectory($root)) {
            throw new InvalidArgumentException("Instagram archive root does not exist: {$root}");
        }

        $requiredFiles = [
            'personal_information/personal_information/personal_information.json',
            'your_instagram_activity/media/posts_1.json',
            'your_instagram_activity/media/profile_photos.json',
        ];

        foreach ($requiredFiles as $relative) {
            if (! File::isFile($root.'/'.$relative)) {
                throw new InvalidArgumentException("Required Instagram archive file is missing: {$relative}");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $profileUser
     * @return array{int, string, string}
     */
    private function importAccount(string $accessMode, array $profileUser): array
    {
        $stringMap = $profileUser['string_map_data'] ?? [];

        $username = (string) ($stringMap['Username']['value'] ?? '');
        $displayName = (string) ($stringMap['Name']['value'] ?? '') ?: null;
        $email = (string) ($stringMap['Email']['value'] ?? '') ?: null;
        $phoneNumber = (string) ($stringMap['Phone Number']['value'] ?? '') ?: null;
        $accountKey = sha1($username);

        $accountId = $this->sourceObservationStore->upsertAndReturnId(
            table: self::TABLE_ACCOUNTS,
            unique: ['account_key' => $accountKey],
            values: [
                'username' => $username,
                'display_name' => $displayName,
                'email' => $email,
                'phone_number' => $phoneNumber,
                'access_mode' => $accessMode,
            ],
        );

        return [$accountId, $accountKey, $username];
    }

    /**
     * @param  array<string, mixed>  $profileUser
     */
    private function importProfileSnapshot(int $accountId, string $accountKey, array $profileUser, Run $run): void
    {
        $stringMap = $profileUser['string_map_data'] ?? [];

        $username = (string) ($stringMap['Username']['value'] ?? '');
        $displayName = (string) ($stringMap['Name']['value'] ?? '') ?: null;
        $email = (string) ($stringMap['Email']['value'] ?? '') ?: null;
        $phoneNumber = (string) ($stringMap['Phone Number']['value'] ?? '') ?: null;

        $rawPayload = json_encode($profileUser, JSON_THROW_ON_ERROR);
        $snapshotKey = sha1($accountKey.$rawPayload);

        $snapshotId = $this->sourceObservationStore->upsertAndReturnId(
            table: self::TABLE_PROFILE_SNAPSHOTS,
            unique: ['snapshot_key' => $snapshotKey],
            values: [
                'instagram_account_id' => $accountId,
                'username' => $username,
                'display_name' => $displayName,
                'email' => $email,
                'phone_number' => $phoneNumber,
                'snapshotted_at' => now(),
                'raw_payload' => $rawPayload,
            ],
        );

        $this->provenanceWriter->link(new WriteProvenanceLinkData(
            runId: $run->id,
            outputTarget: self::TABLE_PROFILE_SNAPSHOTS.':'.$snapshotId,
            claimKey: 'imported-profile-snapshot',
            evidenceType: 'archive-file',
            evidenceRef: 'instagram-archive#personal_information',
        ));
    }

    /**
     * @return array{posts:int, inserted_posts:int, reobserved_posts:int, media_refs:int}
     */
    private function importPosts(string $root, int $accountId, string $accountKey, Run $run, ?callable $progress): array
    {
        $posts = $this->readJson($root.'/your_instagram_activity/media/posts_1.json');
        $counts = ['posts' => 0, 'inserted_posts' => 0, 'reobserved_posts' => 0, 'media_refs' => 0];

        foreach ($posts as $postEntry) {
            $media = $postEntry['media'] ?? [];
            if ($media === []) {
                continue;
            }

            $firstMedia = $media[0];
            $firstUri = (string) ($firstMedia['uri'] ?? '');
            if ($firstUri === '') {
                continue;
            }

            $postKey = sha1($accountKey.$firstUri);
            $caption = (string) ($firstMedia['title'] ?? '') ?: null;
            $postTimestamp = (int) ($firstMedia['creation_timestamp'] ?? 0) ?: null;
            $rawPayload = json_encode($postEntry, JSON_THROW_ON_ERROR);

            $existingId = DB::table(self::TABLE_POSTS)->where('post_key', $postKey)->value('id');

            $postId = $this->sourceObservationStore->upsertAndReturnId(
                table: self::TABLE_POSTS,
                unique: ['post_key' => $postKey],
                values: [
                    'instagram_account_id' => $accountId,
                    'caption' => $caption,
                    'post_timestamp' => $postTimestamp,
                    'media_count' => count($media),
                    'raw_payload' => $rawPayload,
                ],
            );

            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: self::TABLE_POSTS.':'.$postId,
                claimKey: 'imported-post',
                evidenceType: 'archive-file',
                evidenceRef: 'instagram-archive#posts_1:'.$postKey,
            ));

            $counts['posts']++;
            if ($existingId !== null) {
                $counts['reobserved_posts']++;
            } else {
                $counts['inserted_posts']++;
            }

            $counts['media_refs'] += $this->upsertMediaRefs($media, $accountId, $postId, 'post', $accountKey, $run);
        }

        if ($progress !== null) {
            $progress('posts_imported', $counts);
        }

        return $counts;
    }

    private function importProfilePhotos(string $root, int $accountId, string $accountKey, Run $run): int
    {
        $data = $this->readJson($root.'/your_instagram_activity/media/profile_photos.json');
        $photos = $data['ig_profile_picture'] ?? [];

        return $this->upsertMediaRefs($photos, $accountId, null, 'profile_photo', $accountKey, $run);
    }

    /**
     * @return array{count:int, skipped:bool}
     */
    private function importStories(string $root, int $accountId, string $accountKey, Run $run): array
    {
        $storiesPath = $root.'/your_instagram_activity/media/stories.json';

        if (! File::isFile($storiesPath)) {
            return ['count' => 0, 'skipped' => true];
        }

        $data = $this->readJson($storiesPath);
        $stories = $data['ig_stories'] ?? [];

        $count = $this->upsertMediaRefs($stories, $accountId, null, 'story', $accountKey, $run);

        return ['count' => $count, 'skipped' => false];
    }

    /**
     * @param  list<array<string, mixed>>  $mediaItems
     */
    private function upsertMediaRefs(
        array $mediaItems,
        int $accountId,
        ?int $postId,
        string $mediaType,
        string $accountKey,
        Run $run,
    ): int {
        $count = 0;

        foreach ($mediaItems as $item) {
            $uri = (string) ($item['uri'] ?? '');
            if ($uri === '') {
                continue;
            }

            $mediaRefKey = sha1($accountKey.$uri);
            $title = (string) ($item['title'] ?? '') ?: null;
            $creationTimestamp = (int) ($item['creation_timestamp'] ?? 0) ?: null;

            $refId = $this->sourceObservationStore->upsertAndReturnId(
                table: self::TABLE_MEDIA_REFS,
                unique: ['media_ref_key' => $mediaRefKey],
                values: [
                    'instagram_account_id' => $accountId,
                    'instagram_post_id' => $postId,
                    'uri' => $uri,
                    'media_type' => $mediaType,
                    'creation_timestamp' => $creationTimestamp,
                    'title' => $title,
                ],
            );

            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: self::TABLE_MEDIA_REFS.':'.$refId,
                claimKey: 'imported-media-ref',
                evidenceType: 'archive-file',
                evidenceRef: 'instagram-archive#media:'.$uri,
            ));
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! File::isFile($path)) {
            throw new InvalidArgumentException("Instagram archive file not found: {$path}");
        }

        $contents = File::get($path);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Instagram archive file did not decode to an array: {$path}");
        }

        return $decoded;
    }
}
