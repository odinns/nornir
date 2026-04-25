<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\FacebookComment;
use App\Models\FacebookMessage;
use App\Models\FacebookPost;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class FacebookSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'facebook';
    }

    public function build(): iterable
    {
        foreach (FacebookMessage::query()->with(['thread.participants', 'sender'])->lazyById() as $message) {
            yield new SearchDocumentData(
                sourceType: 'facebook',
                sourceTable: 'facebook_messages',
                sourceId: $message->canonical_key,
                title: $message->thread->title,
                body: $message->content,
                occurredAt: $message->sent_at,
                participants: $this->participants(
                    $message->thread->participants->pluck('display_name')->push($message->sender?->display_name)->all(),
                ),
                urlOrLocator: $message->thread->thread_path,
            );
        }

        foreach (FacebookPost::query()->lazyById() as $post) {
            yield new SearchDocumentData(
                sourceType: 'facebook',
                sourceTable: 'facebook_posts',
                sourceId: $post->canonical_key,
                title: $post->title,
                body: $post->content,
                occurredAt: $post->published_at,
            );
        }

        foreach (FacebookComment::query()->lazyById() as $comment) {
            yield new SearchDocumentData(
                sourceType: 'facebook',
                sourceTable: 'facebook_comments',
                sourceId: $comment->canonical_key,
                title: $comment->title,
                body: $comment->content,
                occurredAt: $comment->published_at,
            );
        }
    }
}
