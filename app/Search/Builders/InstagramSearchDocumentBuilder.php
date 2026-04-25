<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\InstagramMediaRef;
use App\Models\InstagramPost;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;
use Carbon\CarbonImmutable;

final class InstagramSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'instagram';
    }

    public function build(): iterable
    {
        foreach (InstagramPost::query()->with('account')->lazyById() as $post) {
            yield new SearchDocumentData(
                sourceType: 'instagram',
                sourceTable: 'instagram_posts',
                sourceId: $post->post_key,
                title: null,
                body: $post->caption,
                occurredAt: $post->post_timestamp === null ? null : CarbonImmutable::createFromTimestampUTC($post->post_timestamp),
                participants: $this->participants([$post->account->username, $post->account->display_name]),
                metadata: ['media_count' => $post->media_count],
            );
        }

        foreach (InstagramMediaRef::query()->with('account')->lazyById() as $media) {
            yield new SearchDocumentData(
                sourceType: 'instagram',
                sourceTable: 'instagram_media_refs',
                sourceId: $media->media_ref_key,
                title: $media->title,
                body: null,
                occurredAt: $media->creation_timestamp === null ? null : CarbonImmutable::createFromTimestampUTC($media->creation_timestamp),
                participants: $this->participants([$media->account->username, $media->account->display_name]),
                urlOrLocator: $media->uri,
                metadata: ['media_type' => $media->media_type],
            );
        }
    }
}
