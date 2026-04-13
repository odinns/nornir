<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * @param  array{
 *     username?: string,
 *     display_name?: string,
 *     email?: string,
 *     phone_number?: string,
 *     profile_photo_uri?: string,
 *     profile_photo_timestamp?: int,
 *     posts?: list<array{
 *         media: list<array{
 *             uri: string,
 *             creation_timestamp: int,
 *             title?: string
 *         }>
 *     }>,
 *     profile_photos?: list<array{uri: string, creation_timestamp: int}>,
 *     stories?: list<array{uri: string, creation_timestamp: int, title?: string}>|false,
 * }  $dataset
 * @return array{archive_path: string}
 */
function createInstagramFixtureArchive(string $name, array $dataset = []): array
{
    $root = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));

    File::ensureDirectoryExists($root.'/personal_information/personal_information');
    File::ensureDirectoryExists($root.'/your_instagram_activity/media');

    // personal_information.json
    File::put(
        $root.'/personal_information/personal_information/personal_information.json',
        json_encode([
            'profile_user' => [[
                'title' => 'User Information',
                'string_map_data' => [
                    'Username' => ['href' => '', 'value' => $dataset['username'] ?? 'testuser', 'timestamp' => 0],
                    'Name' => ['href' => '', 'value' => $dataset['display_name'] ?? 'Test User', 'timestamp' => 0],
                    'Email' => ['href' => '', 'value' => $dataset['email'] ?? 'test@example.com', 'timestamp' => 0],
                    'Phone Number' => ['href' => '', 'value' => $dataset['phone_number'] ?? '', 'timestamp' => 0],
                ],
                'media_map_data' => [
                    'Profile Photo' => [
                        'uri' => $dataset['profile_photo_uri'] ?? 'media/profile/202207/profile.jpg',
                        'creation_timestamp' => $dataset['profile_photo_timestamp'] ?? 1_656_831_379,
                        'title' => '',
                    ],
                ],
            ]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    // posts_1.json
    $posts = $dataset['posts'] ?? [
        [
            'media' => [[
                'uri' => 'media/posts/202401/post-photo-1.jpg',
                'creation_timestamp' => 1_704_362_115,
                'title' => 'A test post caption',
            ]],
        ],
    ];
    File::put(
        $root.'/your_instagram_activity/media/posts_1.json',
        json_encode($posts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    // profile_photos.json
    $profilePhotos = $dataset['profile_photos'] ?? [
        ['uri' => 'media/profile/202207/profile.jpg', 'creation_timestamp' => 1_656_831_379],
    ];
    File::put(
        $root.'/your_instagram_activity/media/profile_photos.json',
        json_encode(['ig_profile_picture' => $profilePhotos], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    // stories.json — omit entirely when $dataset['stories'] === false
    if (($dataset['stories'] ?? null) !== false) {
        $stories = is_array($dataset['stories'] ?? null)
            ? $dataset['stories']
            : [
                ['uri' => 'media/stories/202401/story-1.mp4', 'creation_timestamp' => 1_704_362_200, 'title' => ''],
            ];
        File::put(
            $root.'/your_instagram_activity/media/stories.json',
            json_encode(['ig_stories' => $stories], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }

    return ['archive_path' => $root];
}
