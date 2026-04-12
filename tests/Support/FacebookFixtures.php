<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  array{
 *     profile?:array<string, mixed>,
 *     friends?:list<array{name:string,timestamp?:int}>,
 *     followers?:list<array{name:string,timestamp?:int}>,
 *     following?:list<array{name:string,timestamp?:int}>,
 *     posts?:list<array{
 *         title?:string,
 *         timestamp:int,
 *         post?:string|null,
 *         uri?:string|null
 *     }>,
 *     comments?:list<array{
 *         timestamp:int,
 *         title?:string,
 *         comment:string
 *     }>,
 *     reactions?:list<array{
 *         timestamp:int,
 *         title?:string,
 *         reaction:string,
 *         actor?:string|null
 *     }>,
 *     real_archive_reactions?:list<array{
 *         timestamp:int,
 *         reaction:string,
 *         actor:string,
 *         url?:string|null
 *     }>,
 *     threads?:list<array{
 *         category:string,
 *         thread_key:string,
 *         title?:string|null,
 *         is_still_participant?:bool,
 *         participants:list<string>,
 *         messages:list<array{
 *             sender_name:string,
 *             timestamp_ms:int,
 *             content?:string|null,
 *             is_unsent?:bool,
 *             reactions?:list<array{reaction:string,actor:string}>,
 *             photos?:list<array{uri:string,creation_timestamp?:int}>,
 *             files?:list<array{uri:string,creation_timestamp?:int}>
 *         }>
 *     }>
 * }  $dataset
 * @return array{root_path:string, archive_path:string}
 */
function createFacebookFixtureArchive(string $name, array $dataset): array
{
    $root = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));
    $archivePath = $root.'/facebook';

    File::ensureDirectoryExists($archivePath);

    File::ensureDirectoryExists($archivePath.'/personal_information/profile_information');
    File::put(
        $archivePath.'/personal_information/profile_information/profile_information.json',
        json_encode([
            'profile_v2' => $dataset['profile'] ?? [
                'name' => ['full_name' => 'Odinn Test'],
                'emails' => ['emails' => [['email' => 'odinn@example.com']]],
                'current_city' => ['name' => 'Copenhagen'],
                'hometown' => ['name' => 'Akureyri'],
            ],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    File::ensureDirectoryExists($archivePath.'/connections/friends');
    File::ensureDirectoryExists($archivePath.'/connections/followers');
    File::put(
        $archivePath.'/connections/friends/your_friends.json',
        json_encode([
            'friends_v2' => $dataset['friends'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );
    File::put(
        $archivePath.'/connections/followers/people_who_followed_you.json',
        json_encode([
            'followers_v2' => $dataset['followers'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );
    File::put(
        $archivePath.'/connections/followers/who_you_follow.json',
        json_encode([
            'following_v2' => $dataset['following'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    File::ensureDirectoryExists($archivePath.'/your_facebook_activity/posts');
    File::put(
        $archivePath.'/your_facebook_activity/posts/your_posts_1.json',
        json_encode([
            'status_updates_v2' => array_map(static fn (array $post): array => [
                'timestamp' => $post['timestamp'],
                'title' => $post['title'] ?? 'Post',
                'data' => [['post' => $post['post'] ?? null]],
                'attachments' => isset($post['uri']) ? [[
                    'data' => [[
                        'media' => [
                            'uri' => $post['uri'],
                            'creation_timestamp' => $post['timestamp'],
                        ],
                    ]],
                ]] : [],
            ], $dataset['posts'] ?? []),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    File::ensureDirectoryExists($archivePath.'/your_facebook_activity/comments_and_reactions');
    File::put(
        $archivePath.'/your_facebook_activity/comments_and_reactions/comments.json',
        json_encode([
            'comments_v2' => array_map(static fn (array $comment): array => [
                'timestamp' => $comment['timestamp'],
                'title' => $comment['title'] ?? 'Comment',
                'data' => [[
                    'comment' => [
                        'comment' => $comment['comment'],
                    ],
                ]],
            ], $dataset['comments'] ?? []),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );
    File::put(
        $archivePath.'/your_facebook_activity/comments_and_reactions/reactions.json',
        json_encode([
            'reactions_v2' => array_map(static fn (array $reaction): array => [
                'timestamp' => $reaction['timestamp'],
                'title' => $reaction['title'] ?? 'Reaction',
                'data' => [[
                    'reaction' => [
                        'reaction' => $reaction['reaction'],
                        'actor' => $reaction['actor'] ?? null,
                    ],
                ]],
            ], $dataset['reactions'] ?? []),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );
    File::put(
        $archivePath.'/your_facebook_activity/comments_and_reactions/likes_and_reactions.json',
        json_encode(array_map(static fn (array $reaction): array => [
            'timestamp' => $reaction['timestamp'],
            'fbid' => (string) ($reaction['timestamp'].'-reaction'),
            'label_values' => [
                [
                    'label' => 'Reaktion',
                    'value' => $reaction['reaction'],
                ],
                [
                    'label' => 'Webadresse',
                    'value' => $reaction['url'] ?? 'https://www.facebook.com/example',
                    'href' => $reaction['url'] ?? 'https://www.facebook.com/example',
                ],
                [
                    'label' => 'Navn',
                    'value' => $reaction['actor'],
                ],
            ],
            'media' => [],
        ], $dataset['real_archive_reactions'] ?? []), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    foreach ($dataset['threads'] ?? [] as $thread) {
        $threadDirectory = $archivePath.'/your_facebook_activity/messages/'.$thread['category'].'/'.$thread['thread_key'];
        File::ensureDirectoryExists($threadDirectory);

        foreach ($thread['messages'] as $message) {
            foreach ($message['photos'] ?? [] as $photo) {
                $photoPath = $threadDirectory.'/'.$photo['uri'];
                File::ensureDirectoryExists(dirname($photoPath));
                File::put($photoPath, 'photo');
            }

            foreach ($message['files'] ?? [] as $file) {
                $filePath = $threadDirectory.'/'.$file['uri'];
                File::ensureDirectoryExists(dirname($filePath));
                File::put($filePath, 'file');
            }
        }

        File::put(
            $threadDirectory.'/message_1.json',
            json_encode([
                'participants' => array_map(static fn (string $name): array => ['name' => $name], $thread['participants']),
                'title' => $thread['title'] ?? null,
                'is_still_participant' => $thread['is_still_participant'] ?? true,
                'thread_path' => 'your_facebook_activity/messages/'.$thread['category'].'/'.$thread['thread_key'],
                'messages' => array_map(static fn (array $message): array => array_filter([
                    'sender_name' => $message['sender_name'],
                    'timestamp_ms' => $message['timestamp_ms'],
                    'content' => $message['content'] ?? null,
                    'is_unsent' => $message['is_unsent'] ?? false,
                    'reactions' => $message['reactions'] ?? [],
                    'photos' => $message['photos'] ?? [],
                    'files' => $message['files'] ?? [],
                ], static fn (mixed $value): bool => $value !== null), $thread['messages']),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }

    return [
        'root_path' => $root,
        'archive_path' => $archivePath,
    ];
}
