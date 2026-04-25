<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Import\Support\ImportArtifactWriter;
use App\Actions\Import\Support\ImportRunExecutor;
use App\Actions\Import\Support\SourceObservationStore;
use App\Data\Import\FacebookImportResultData;
use App\Data\Intake\ImporterDispatchData;
use App\Data\Shared\WriteProvenanceLinkData;
use App\Models\Run;
use App\Services\Nornir\ProvenanceWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ImportFacebookArchiveAction
{
    /**
     * @var array<string, int>
     */
    private array $personIdCache = [];

    public function __construct(
        private readonly ImportRunExecutor $importRunExecutor,
        private readonly ImportArtifactWriter $importArtifactWriter,
        private readonly SourceObservationStore $sourceObservationStore,
        private readonly ProvenanceWriter $provenanceWriter,
    ) {}

    public function __invoke(ImporterDispatchData $dispatchPayload, ?callable $progress = null): FacebookImportResultData
    {
        $execution = $this->importRunExecutor->execute(
            dispatchPayload: $dispatchPayload,
            operation: 'facebook-import',
            import: fn (Run $run): array => $this->importArchive($dispatchPayload, $run, $progress),
            writeArtifacts: function (Run $run, array $summary) use ($dispatchPayload): void {
                $this->importArtifactWriter->write($run, $dispatchPayload, 'facebook', 'facebook-import-summary', $summary);
            },
        );

        return new FacebookImportResultData(
            run: $execution['run'],
            summary: $execution['summary'],
        );
    }

    /**
     * @return array<string, int|string>
     */
    private function importArchive(ImporterDispatchData $dispatchPayload, Run $run, ?callable $progress): array
    {
        $archivePath = $this->resolveArchivePath($dispatchPayload);
        $this->personIdCache = [];

        $summary = [
            'source_file' => basename($archivePath),
            'source_set_id' => 0,
            'people' => 0,
            'profile_snapshots' => 0,
            'social_edges' => 0,
            'threads' => 0,
            'messages' => 0,
            'posts' => 0,
            'comments' => 0,
            'reactions' => 0,
            'attachments' => 0,
            'inserted_messages' => 0,
            'reobserved_messages' => 0,
        ];

        $archiveId = DB::transaction(function () use ($archivePath, $dispatchPayload): int {
            return $this->sourceObservationStore->upsertAndReturnId(
                table: 'facebook_archives',
                unique: [
                    'source_key' => sha1($archivePath),
                ],
                values: [
                    'source_locator' => $archivePath,
                    'access_mode' => $dispatchPayload->accessMode,
                ],
            );
        });
        $summary['source_set_id'] = $archiveId;

        $observedPeople = [];

        $profilePersonId = DB::transaction(function () use ($archivePath, $archiveId, &$observedPeople): ?int {
            return $this->importProfileSnapshot($archivePath, $archiveId, $observedPeople);
        });

        if ($profilePersonId !== null) {
            $observedPeople[$profilePersonId] = true;
        }

        $summary['profile_snapshots'] = (int) DB::table('facebook_profile_snapshots')
            ->where('facebook_archive_id', $archiveId)
            ->count();

        $summary['social_edges'] = DB::transaction(function () use ($archivePath, $archiveId, &$observedPeople): int {
            return $this->importSocialEdges($archivePath, $archiveId, $observedPeople);
        });

        $summary['posts'] = DB::transaction(function () use ($archivePath, $archiveId, $run, &$summary): int {
            return $this->importPosts($archivePath, $archiveId, $run, $summary);
        });

        $summary['comments'] = DB::transaction(function () use ($archivePath, $archiveId, $run): int {
            return $this->importComments($archivePath, $archiveId, $run);
        });

        $summary['reactions'] = DB::transaction(function () use ($archivePath, $archiveId, $run, &$observedPeople): int {
            return $this->importArchiveReactions($archivePath, $archiveId, $run, $observedPeople);
        });

        $threadSummary = $this->importThreads($archivePath, $archiveId, $run, $observedPeople, $progress);
        $summary['threads'] = $threadSummary['threads'];
        $summary['messages'] = $threadSummary['messages'];
        $summary['attachments'] = $threadSummary['attachments'] + (int) $summary['attachments'];
        $summary['inserted_messages'] = $threadSummary['inserted_messages'];
        $summary['reobserved_messages'] = $threadSummary['reobserved_messages'];
        $summary['people'] = count($observedPeople);

        return $summary;
    }

    private function resolveArchivePath(ImporterDispatchData $dispatchPayload): string
    {
        if ($dispatchPayload->accessMode !== 'local-path') {
            throw new InvalidArgumentException('Facebook imports currently require a local-path archive directory.');
        }

        if (! File::isDirectory($dispatchPayload->sourceLocator)) {
            throw new InvalidArgumentException('Malformed Facebook source payload: archive directory was not found.');
        }

        return $dispatchPayload->sourceLocator;
    }

    /**
     * @param  array<int, true>  $observedPeople
     */
    private function importProfileSnapshot(string $archivePath, int $archiveId, array &$observedPeople): ?int
    {
        $profilePath = $archivePath.'/personal_information/profile_information/profile_information.json';

        if (! File::exists($profilePath)) {
            return null;
        }

        $profile = $this->readJsonFile($profilePath)['profile_v2'] ?? null;

        if (! is_array($profile)) {
            return null;
        }

        $fullName = $this->normalizeString(data_get($profile, 'name.full_name'));
        $personId = $fullName !== null ? $this->upsertPersonByName($fullName) : null;

        if ($personId !== null) {
            $observedPeople[$personId] = true;
        }

        DB::table('facebook_profile_snapshots')->updateOrInsert(
            ['facebook_archive_id' => $archiveId],
            [
                'facebook_person_id' => $personId,
                'full_name' => $fullName,
                'emails_json' => json_encode(data_get($profile, 'emails.emails'), JSON_THROW_ON_ERROR),
                'current_city' => $this->normalizeString(data_get($profile, 'current_city.name')),
                'hometown' => $this->normalizeString(data_get($profile, 'hometown.name')),
                'raw_profile' => json_encode($profile, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return $personId;
    }

    /**
     * @param  array<int, true>  $observedPeople
     */
    private function importSocialEdges(string $archivePath, int $archiveId, array &$observedPeople): int
    {
        $imports = [
            ['path' => 'connections/friends/your_friends.json', 'key' => 'friends_v2', 'edge_type' => 'friend'],
            ['path' => 'connections/followers/people_who_followed_you.json', 'key' => 'followers_v2', 'edge_type' => 'follower'],
            ['path' => 'connections/followers/who_you_follow.json', 'key' => 'following_v2', 'edge_type' => 'following'],
        ];

        $count = 0;

        foreach ($imports as $import) {
            $path = $archivePath.'/'.$import['path'];

            if (! File::exists($path)) {
                continue;
            }

            $entries = $this->readJsonFile($path)[$import['key']] ?? [];

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $name = $this->normalizeString($entry['name'] ?? null);

                if ($name === null) {
                    continue;
                }

                $personId = $this->upsertPersonByName($name);
                $observedPeople[$personId] = true;

                DB::table('facebook_social_edges')->updateOrInsert(
                    [
                        'facebook_archive_id' => $archiveId,
                        'facebook_person_id' => $personId,
                        'edge_type' => $import['edge_type'],
                    ],
                    [
                        'observed_at' => $this->timestampColumnValue($entry['timestamp'] ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function importPosts(string $archivePath, int $archiveId, Run $run, array &$summary): int
    {
        $count = 0;

        foreach (File::glob($archivePath.'/your_facebook_activity/posts/*.json') as $path) {
            $posts = $this->readJsonFile($path)['status_updates_v2'] ?? [];

            if (! is_array($posts)) {
                continue;
            }

            foreach ($posts as $post) {
                if (! is_array($post)) {
                    continue;
                }

                $timestamp = $this->integerValue($post['timestamp'] ?? null);
                $title = $this->normalizeString($post['title'] ?? null);
                $content = $this->normalizeString(data_get($post, 'data.0.post'));
                $canonicalKey = sha1(json_encode([$timestamp, $title, $content], JSON_THROW_ON_ERROR));

                $postRow = $this->upsertCanonicalRow(
                    table: 'facebook_posts',
                    unique: ['canonical_key' => $canonicalKey],
                    values: [
                        'facebook_archive_id' => $archiveId,
                        'published_timestamp' => $timestamp,
                        'published_at' => $this->timestampColumnValue($timestamp),
                        'title' => $title,
                        'content' => $content,
                        'raw_post' => json_encode($post, JSON_THROW_ON_ERROR),
                    ],
                );

                $this->sourceObservationStore->record(
                    table: 'facebook_post_observations',
                    unique: [
                        'facebook_post_id' => $postRow['id'],
                        'facebook_archive_id' => $archiveId,
                    ],
                );

                foreach ($this->extractPostAttachments($post) as $attachment) {
                    $this->upsertAttachment(
                        sourceContext: 'post',
                        attachmentType: $attachment['attachment_type'],
                        relativePath: $attachment['relative_path'],
                        sourceUri: $attachment['source_uri'],
                        createdTimestamp: $attachment['created_timestamp'],
                        fileSizeBytes: $this->resolveAttachmentSize($archivePath, $attachment['relative_path']),
                        uniqueSeed: $canonicalKey,
                        messageId: null,
                        postId: $postRow['id'],
                        rawAttachment: $attachment['raw_attachment'],
                    );
                    $summary['attachments']++;
                }

                $this->provenanceWriter->link(new WriteProvenanceLinkData(
                    runId: $run->id,
                    outputTarget: 'facebook_posts:'.$postRow['id'],
                    claimKey: 'imported-post',
                    evidenceType: 'source-file',
                    evidenceRef: basename((string) $path).'#post:'.$canonicalKey,
                ));

                $count++;
            }
        }

        return $count;
    }

    private function importComments(string $archivePath, int $archiveId, Run $run): int
    {
        $path = $archivePath.'/your_facebook_activity/comments_and_reactions/comments.json';

        if (! File::exists($path)) {
            return 0;
        }

        $comments = $this->readJsonFile($path)['comments_v2'] ?? [];

        if (! is_array($comments)) {
            return 0;
        }

        $count = 0;

        foreach ($comments as $comment) {
            if (! is_array($comment)) {
                continue;
            }

            $timestamp = $this->integerValue($comment['timestamp'] ?? null);
            $title = $this->normalizeString($comment['title'] ?? null);
            $content = $this->normalizeString(data_get($comment, 'data.0.comment.comment'));
            $canonicalKey = sha1(json_encode([$timestamp, $title, $content], JSON_THROW_ON_ERROR));

            $commentRow = $this->upsertCanonicalRow(
                table: 'facebook_comments',
                unique: ['canonical_key' => $canonicalKey],
                values: [
                    'facebook_archive_id' => $archiveId,
                    'published_timestamp' => $timestamp,
                    'published_at' => $this->timestampColumnValue($timestamp),
                    'title' => $title,
                    'content' => $content,
                    'raw_comment' => json_encode($comment, JSON_THROW_ON_ERROR),
                ],
            );

            $this->sourceObservationStore->record(
                table: 'facebook_comment_observations',
                unique: [
                    'facebook_comment_id' => $commentRow['id'],
                    'facebook_archive_id' => $archiveId,
                ],
            );

            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: 'facebook_comments:'.$commentRow['id'],
                claimKey: 'imported-comment',
                evidenceType: 'source-file',
                evidenceRef: 'comments.json#comment:'.$canonicalKey,
            ));

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, true>  $observedPeople
     */
    private function importArchiveReactions(string $archivePath, int $archiveId, Run $run, array &$observedPeople): int
    {
        $paths = [
            $archivePath.'/your_facebook_activity/comments_and_reactions/reactions.json',
            ...array_values(File::glob($archivePath.'/your_facebook_activity/comments_and_reactions/likes_and_reactions*.json') ?: []),
        ];
        $count = 0;

        foreach (array_values(array_unique($paths)) as $path) {
            if (! File::exists($path)) {
                continue;
            }

            foreach ($this->extractArchiveReactions($path) as $reaction) {
                $timestamp = $this->integerValue($reaction['timestamp'] ?? null);
                $title = $this->normalizeString($reaction['title'] ?? null);
                $reactionName = $this->normalizeString($reaction['reaction'] ?? null);

                if ($reactionName === null) {
                    continue;
                }

                $actor = $this->normalizeString($reaction['actor'] ?? null);
                $personId = $actor !== null ? $this->upsertPersonByName($actor) : null;

                if ($personId !== null) {
                    $observedPeople[$personId] = true;
                }

                $canonicalKey = sha1(json_encode([$timestamp, $title, $reactionName, $actor], JSON_THROW_ON_ERROR));
                $reactionRow = $this->upsertCanonicalRow(
                    table: 'facebook_reactions',
                    unique: ['canonical_key' => $canonicalKey],
                    values: [
                        'facebook_archive_id' => $archiveId,
                        'facebook_person_id' => $personId,
                        'published_timestamp' => $timestamp,
                        'published_at' => $this->timestampColumnValue($timestamp),
                        'title' => $title,
                        'reaction' => $reactionName,
                        'raw_reaction' => json_encode($reaction, JSON_THROW_ON_ERROR),
                    ],
                );

                $this->sourceObservationStore->record(
                    table: 'facebook_reaction_observations',
                    unique: [
                        'facebook_reaction_id' => $reactionRow['id'],
                        'facebook_archive_id' => $archiveId,
                    ],
                );

                $this->provenanceWriter->link(new WriteProvenanceLinkData(
                    runId: $run->id,
                    outputTarget: 'facebook_reactions:'.$reactionRow['id'],
                    claimKey: 'imported-reaction',
                    evidenceType: 'source-file',
                    evidenceRef: basename((string) $path).'#reaction:'.$canonicalKey,
                ));

                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<array{timestamp:?int,title:?string,reaction:?string,actor:?string}>
     */
    private function extractArchiveReactions(string $path): array
    {
        $payload = $this->readJsonFile($path);
        $reactions = [];

        if (isset($payload['reactions_v2']) && is_array($payload['reactions_v2'])) {
            foreach ($payload['reactions_v2'] as $reaction) {
                if (! is_array($reaction)) {
                    continue;
                }

                $reactions[] = [
                    'timestamp' => $this->integerValue($reaction['timestamp'] ?? null),
                    'title' => $this->normalizeString($reaction['title'] ?? null),
                    'reaction' => $this->normalizeString(data_get($reaction, 'data.0.reaction.reaction')),
                    'actor' => $this->normalizeString(data_get($reaction, 'data.0.reaction.actor')),
                ];
            }

            return $reactions;
        }

        foreach ($payload as $reaction) {
            if (! is_array($reaction)) {
                continue;
            }

            $labelValues = $reaction['label_values'] ?? [];

            if (! is_array($labelValues)) {
                continue;
            }

            $reactions[] = [
                'timestamp' => $this->integerValue($reaction['timestamp'] ?? null),
                'title' => $this->firstReactionLabelValue($labelValues, ['Webadresse', 'Title', 'Titel']),
                'reaction' => $this->firstReactionLabelValue($labelValues, ['Reaktion', 'Reaction']),
                'actor' => $this->firstReactionLabelValue($labelValues, ['Navn', 'Name']),
            ];
        }

        return $reactions;
    }

    /**
     * @param  array<mixed>  $items
     * @param  list<string>  $labels
     */
    private function firstReactionLabelValue(array $items, array $labels): ?string
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = $this->normalizeString($item['label'] ?? null);

            if ($label !== null && in_array($label, $labels, true)) {
                $value = $this->normalizeString($item['value'] ?? null)
                    ?? $this->normalizeString($item['href'] ?? null);

                if ($value !== null) {
                    return $value;
                }
            }

            $dicts = $item['dict'] ?? null;

            if (! is_array($dicts)) {
                continue;
            }

            foreach ($dicts as $dict) {
                if (! is_array($dict)) {
                    continue;
                }

                $nestedValue = $this->firstReactionLabelValue(
                    is_array($dict['dict'] ?? null) ? $dict['dict'] : [],
                    $labels,
                );

                if ($nestedValue !== null) {
                    return $nestedValue;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, true>  $observedPeople
     * @return array{threads:int,messages:int,attachments:int,inserted_messages:int,reobserved_messages:int}
     */
    private function importThreads(
        string $archivePath,
        int $archiveId,
        Run $run,
        array &$observedPeople,
        ?callable $progress,
    ): array {
        $messageDirectories = File::glob($archivePath.'/your_facebook_activity/messages/*/*', GLOB_ONLYDIR);
        $threadCount = count($messageDirectories);
        $messageCount = 0;
        $attachmentCount = 0;
        $insertedMessages = 0;
        $reobservedMessages = 0;

        if ($threadCount > 0) {
            $this->reportProgress($progress, 'threads_resolved', [
                'total_threads' => $threadCount,
            ]);
        }

        foreach ($messageDirectories ?: [] as $index => $threadDirectory) {
            $threadSummary = DB::transaction(function () use (
                $archiveId,
                $archivePath,
                $run,
                $threadDirectory,
                &$observedPeople
            ): array {
                return $this->importThread($archivePath, $archiveId, $run, $threadDirectory, $observedPeople);
            });

            $threadKey = $threadSummary['thread_key'];
            $messageCount += $threadSummary['messages'];
            $attachmentCount += $threadSummary['attachments'];
            $insertedMessages += $threadSummary['inserted_messages'];
            $reobservedMessages += $threadSummary['reobserved_messages'];

            $this->reportProgress($progress, 'thread_completed', [
                'thread' => $threadKey,
                'current_thread' => $index + 1,
                'total_threads' => $threadCount,
                'messages' => $messageCount,
            ]);
        }

        return [
            'threads' => $threadCount,
            'messages' => $messageCount,
            'attachments' => $attachmentCount,
            'inserted_messages' => $insertedMessages,
            'reobserved_messages' => $reobservedMessages,
        ];
    }

    /**
     * @param  array<int, true>  $observedPeople
     * @return array{
     *     thread_key:string,
     *     messages:int,
     *     attachments:int,
     *     inserted_messages:int,
     *     reobserved_messages:int
     * }
     */
    private function importThread(
        string $archivePath,
        int $archiveId,
        Run $run,
        string $threadDirectory,
        array &$observedPeople,
    ): array {
        $category = basename(dirname($threadDirectory));
        $threadKey = basename($threadDirectory);
        $chunkFiles = File::glob($threadDirectory.'/message_*.json');

        $participants = [];
        $allMessages = [];
        $threadTitle = null;
        $isStillParticipant = false;
        $threadPath = null;

        foreach ($chunkFiles ?: [] as $chunkFile) {
            $payload = $this->readJsonFile($chunkFile);
            $threadTitle ??= $this->normalizeString($payload['title'] ?? null);
            $isStillParticipant = (bool) ($payload['is_still_participant'] ?? false);
            $threadPath ??= $this->normalizeString($payload['thread_path'] ?? null);

            foreach ($payload['participants'] ?? [] as $participant) {
                if (! is_array($participant)) {
                    continue;
                }

                $name = $this->normalizeString($participant['name'] ?? null);

                if ($name !== null) {
                    $participants[$name] = $name;
                }
            }

            foreach ($payload['messages'] ?? [] as $message) {
                if (is_array($message)) {
                    $allMessages[] = $message;
                }
            }
        }

        usort($allMessages, static fn (array $left, array $right): int => ($left['timestamp_ms'] ?? 0) <=> ($right['timestamp_ms'] ?? 0));

        $threadRow = $this->upsertCanonicalRow(
            table: 'facebook_threads',
            unique: ['thread_key' => $threadKey],
            values: [
                'facebook_archive_id' => $archiveId,
                'thread_uid' => $this->deriveThreadUid($threadKey),
                'category' => $category,
                'title' => $threadTitle,
                'is_still_participant' => $isStillParticipant,
                'thread_path' => $threadPath ?? 'your_facebook_activity/messages/'.$category.'/'.$threadKey,
                'message_count' => count($allMessages),
                'first_message_at' => $this->timestampMsColumnValue($allMessages[0]['timestamp_ms'] ?? null),
                'last_message_at' => $this->timestampMsColumnValue($allMessages[count($allMessages) - 1]['timestamp_ms'] ?? null),
                'raw_thread' => json_encode([
                    'participants' => array_values($participants),
                    'message_chunks' => count($chunkFiles ?: []),
                ], JSON_THROW_ON_ERROR),
            ],
        );

        foreach ($participants as $name) {
            $personId = $this->upsertPersonByName($name);
            $observedPeople[$personId] = true;

            $this->sourceObservationStore->record(
                table: 'facebook_thread_participants',
                unique: [
                    'facebook_thread_id' => $threadRow['id'],
                    'facebook_person_id' => $personId,
                ],
            );
        }

        $messageCount = 0;
        $attachmentCount = 0;
        $insertedMessages = 0;
        $reobservedMessages = 0;

        foreach ($allMessages as $message) {
            $senderName = $this->normalizeString($message['sender_name'] ?? null);
            $senderId = $senderName !== null ? $this->upsertPersonByName($senderName) : null;

            if ($senderId !== null) {
                $observedPeople[$senderId] = true;
            }

            $content = $this->normalizeString($message['content'] ?? null);
            $timestampMs = $this->integerValue($message['timestamp_ms'] ?? null) ?? 0;
            $canonicalKey = sha1(json_encode([
                $threadKey,
                $timestampMs,
                $senderName,
                $content,
            ], JSON_THROW_ON_ERROR));

            $messageRow = $this->upsertCanonicalRow(
                table: 'facebook_messages',
                unique: ['canonical_key' => $canonicalKey],
                values: [
                    'facebook_thread_id' => $threadRow['id'],
                    'sender_facebook_person_id' => $senderId,
                    'timestamp_ms' => $timestampMs,
                    'sent_at' => $this->timestampMsColumnValue($timestampMs),
                    'content' => $content,
                    'is_unsent' => (bool) ($message['is_unsent'] ?? false),
                    'raw_message' => json_encode($message, JSON_THROW_ON_ERROR),
                ],
            );

            $this->sourceObservationStore->record(
                table: 'facebook_message_observations',
                unique: [
                    'facebook_message_id' => $messageRow['id'],
                    'facebook_archive_id' => $archiveId,
                ],
            );

            if ($messageRow['wasRecentlyCreated']) {
                $insertedMessages++;
            } else {
                $reobservedMessages++;
            }

            foreach ($message['reactions'] ?? [] as $reaction) {
                if (! is_array($reaction)) {
                    continue;
                }

                $actor = $this->normalizeString($reaction['actor'] ?? null);
                $personId = $actor !== null ? $this->upsertPersonByName($actor) : null;

                if ($personId !== null) {
                    $observedPeople[$personId] = true;
                }

                DB::table('facebook_message_reactions')->updateOrInsert(
                    [
                        'reaction_key' => sha1($canonicalKey.'|'.($reaction['reaction'] ?? '').'|'.($actor ?? '')),
                    ],
                    [
                        'facebook_message_id' => $messageRow['id'],
                        'facebook_person_id' => $personId,
                        'reaction' => $this->normalizeString($reaction['reaction'] ?? null) ?? 'unknown',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            foreach ($this->extractMessageAttachments($message, $threadPath ?? 'your_facebook_activity/messages/'.$category.'/'.$threadKey) as $attachment) {
                $this->upsertAttachment(
                    sourceContext: 'message',
                    attachmentType: $attachment['attachment_type'],
                    relativePath: $attachment['relative_path'],
                    sourceUri: $attachment['source_uri'],
                    createdTimestamp: $attachment['created_timestamp'],
                    fileSizeBytes: $this->resolveAttachmentSize($archivePath, $attachment['relative_path']),
                    uniqueSeed: $canonicalKey,
                    messageId: $messageRow['id'],
                    postId: null,
                    rawAttachment: $attachment['raw_attachment'],
                );
                $attachmentCount++;
            }

            $this->provenanceWriter->link(new WriteProvenanceLinkData(
                runId: $run->id,
                outputTarget: 'facebook_messages:'.$messageRow['id'],
                claimKey: 'imported-message',
                evidenceType: 'source-file',
                evidenceRef: $threadKey.'#message:'.$canonicalKey,
            ));

            $messageCount++;
        }

        return [
            'thread_key' => $threadKey,
            'messages' => $messageCount,
            'attachments' => $attachmentCount,
            'inserted_messages' => $insertedMessages,
            'reobserved_messages' => $reobservedMessages,
        ];
    }

    private function upsertPersonByName(string $name): int
    {
        $normalizedName = mb_strtolower(trim($name));
        $personKey = sha1($normalizedName);

        if (array_key_exists($personKey, $this->personIdCache)) {
            return $this->personIdCache[$personKey];
        }

        $personId = $this->sourceObservationStore->upsertAndReturnId(
            table: 'facebook_people',
            unique: [
                'person_key' => $personKey,
            ],
            values: [
                'display_name' => $name,
                'normalized_name' => $normalizedName,
            ],
        );

        $this->personIdCache[$personKey] = $personId;

        return $personId;
    }

    /**
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     * @return array{id:int,wasRecentlyCreated:bool}
     */
    private function upsertCanonicalRow(string $table, array $unique, array $values): array
    {
        $query = DB::table($table);

        foreach ($unique as $column => $value) {
            $query->where($column, $value);
        }

        $existing = $query->first();

        if ($existing !== null) {
            DB::table($table)
                ->where('id', $existing->id)
                ->update([
                    ...$values,
                    'updated_at' => now(),
                ]);

            return [
                'id' => (int) $existing->id,
                'wasRecentlyCreated' => false,
            ];
        }

        $id = (int) DB::table($table)->insertGetId([
            ...$unique,
            ...$values,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'wasRecentlyCreated' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
    {
        $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function deriveThreadUid(string $threadKey): ?string
    {
        if (preg_match('/(?:^|_)(\d+)$/', $threadKey, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array{
     *     attachment_type:string,
     *     relative_path:?string,
     *     source_uri:?string,
     *     created_timestamp:?int,
     *     raw_attachment:string
     * }>
     */
    private function extractMessageAttachments(array $message, string $threadPath): array
    {
        $attachments = [];

        foreach (['photos' => 'photo', 'files' => 'file', 'videos' => 'video', 'audio_files' => 'audio'] as $key => $type) {
            foreach ($message[$key] ?? [] as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                $uri = $this->normalizeString($attachment['uri'] ?? null);
                $resolvedLocation = $this->resolveAttachmentLocation($uri, $threadPath);

                $attachments[] = [
                    'attachment_type' => $type,
                    'relative_path' => $resolvedLocation['relative_path'],
                    'source_uri' => $resolvedLocation['source_uri'],
                    'created_timestamp' => $this->integerValue($attachment['creation_timestamp'] ?? null),
                    'raw_attachment' => json_encode($attachment, JSON_THROW_ON_ERROR),
                ];
            }
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return list<array{
     *     attachment_type:string,
     *     relative_path:?string,
     *     source_uri:?string,
     *     created_timestamp:?int,
     *     raw_attachment:string
     * }>
     */
    private function extractPostAttachments(array $post): array
    {
        $attachments = [];

        foreach ($post['attachments'] ?? [] as $attachmentGroup) {
            if (! is_array($attachmentGroup)) {
                continue;
            }

            foreach ($attachmentGroup['data'] ?? [] as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                $uri = $this->normalizeString(data_get($attachment, 'media.uri'));
                $resolvedLocation = $this->resolveAttachmentLocation($uri);

                $attachments[] = [
                    'attachment_type' => 'photo',
                    'relative_path' => $resolvedLocation['relative_path'],
                    'source_uri' => $resolvedLocation['source_uri'],
                    'created_timestamp' => $this->integerValue(data_get($attachment, 'media.creation_timestamp')),
                    'raw_attachment' => json_encode($attachment, JSON_THROW_ON_ERROR),
                ];
            }
        }

        return $attachments;
    }

    private function upsertAttachment(
        string $sourceContext,
        string $attachmentType,
        ?string $relativePath,
        ?string $sourceUri,
        ?int $createdTimestamp,
        ?int $fileSizeBytes,
        string $uniqueSeed,
        ?int $messageId,
        ?int $postId,
        string $rawAttachment,
    ): void {
        DB::table('facebook_attachments')->updateOrInsert(
            [
                'attachment_key' => sha1($sourceContext.'|'.$uniqueSeed.'|'.($sourceUri ?? $relativePath ?? '')),
            ],
            [
                'facebook_message_id' => $messageId,
                'facebook_post_id' => $postId,
                'source_context' => $sourceContext,
                'attachment_type' => $attachmentType,
                'relative_path' => $relativePath,
                'source_uri' => $sourceUri,
                'created_timestamp' => $createdTimestamp,
                'file_size_bytes' => $fileSizeBytes,
                'raw_attachment' => $rawAttachment,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function resolveAttachmentSize(string $archivePath, ?string $relativePath): ?int
    {
        if ($relativePath === null) {
            return null;
        }

        $path = $archivePath.'/'.$relativePath;

        if (! File::exists($path) || ! File::isFile($path)) {
            return null;
        }

        return (int) File::size($path);
    }

    /**
     * @return array{relative_path:?string,source_uri:?string}
     */
    private function resolveAttachmentLocation(?string $uri, ?string $basePath = null): array
    {
        if ($uri === null) {
            return [
                'relative_path' => null,
                'source_uri' => null,
            ];
        }

        if (preg_match('#^https?://#i', $uri) === 1) {
            return [
                'relative_path' => null,
                'source_uri' => $uri,
            ];
        }

        $relativePath = $basePath !== null ? ltrim($basePath.'/'.$uri, '/') : ltrim($uri, '/');

        return [
            'relative_path' => $relativePath,
            'source_uri' => $uri,
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            if (preg_match('/Ã|Â|â/u', $normalized) !== 1) {
                break;
            }

            $decoded = mb_convert_encoding($normalized, 'ISO-8859-1', 'UTF-8');

            if (! mb_check_encoding($decoded, 'UTF-8') || $decoded === $normalized) {
                break;
            }

            $normalized = $decoded;
        }

        return $normalized;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function timestampColumnValue(mixed $seconds): ?string
    {
        $value = $this->integerValue($seconds);

        return $value === null ? null : CarbonImmutable::createFromTimestampUTC($value)->toDateTimeString();
    }

    private function timestampMsColumnValue(mixed $milliseconds): ?string
    {
        $value = $this->integerValue($milliseconds);

        return $value === null ? null : CarbonImmutable::createFromTimestampMsUTC($value)->toDateTimeString();
    }

    /**
     * @param  array<string, int|string>  $payload
     */
    private function reportProgress(?callable $progress, string $event, array $payload): void
    {
        if ($progress !== null) {
            $progress($event, $payload);
        }
    }
}
