<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\LinkedinComment;
use App\Models\LinkedinMessage;
use App\Models\LinkedinPosition;
use App\Models\LinkedinProfileSnapshot;
use App\Models\LinkedinProject;
use App\Models\LinkedinRichMedia;
use App\Models\LinkedinShare;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class LinkedinSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'linkedin';
    }

    public function build(): iterable
    {
        foreach (LinkedinProfileSnapshot::query()->lazyById() as $profile) {
            yield new SearchDocumentData(
                sourceType: 'linkedin',
                sourceTable: 'linkedin_profile_snapshots',
                sourceId: (string) $profile->id,
                title: $profile->full_name ?? trim($profile->first_name.' '.$profile->last_name),
                body: $this->joinText([$profile->headline, $profile->summary, $profile->industry, $profile->geo_location]),
                occurredAt: $profile->registered_at,
                participants: $this->participants([$profile->full_name]),
            );
        }

        foreach (LinkedinMessage::query()->with(['conversation', 'sender'])->lazyById() as $message) {
            $conversation = $message->conversation;

            if ($conversation === null) {
                continue;
            }

            yield new SearchDocumentData(
                sourceType: 'linkedin',
                sourceTable: 'linkedin_messages',
                sourceId: $message->canonical_key,
                title: $message->subject ?? $conversation->title,
                body: $message->content,
                occurredAt: $message->sent_at,
                participants: $this->participants([$message->sender?->display_name, $message->to_display]),
                metadata: ['conversation_key' => $conversation->conversation_key, 'folder' => $message->folder],
            );
        }

        foreach (LinkedinShare::query()->lazyById() as $share) {
            yield new SearchDocumentData(
                sourceType: 'linkedin',
                sourceTable: 'linkedin_shares',
                sourceId: $share->canonical_key,
                title: null,
                body: $share->commentary,
                occurredAt: $share->shared_at,
                urlOrLocator: $share->share_link ?? $share->shared_url,
                metadata: ['visibility' => $share->visibility],
            );
        }

        foreach (LinkedinComment::query()->lazyById() as $comment) {
            yield new SearchDocumentData(
                sourceType: 'linkedin',
                sourceTable: 'linkedin_comments',
                sourceId: $comment->canonical_key,
                title: null,
                body: $comment->message,
                occurredAt: $comment->commented_at,
                urlOrLocator: $comment->link,
            );
        }

        foreach (LinkedinPosition::query()->lazyById() as $position) {
            yield new SearchDocumentData(
                sourceType: 'linkedin',
                sourceTable: 'linkedin_positions',
                sourceId: $position->canonical_key,
                title: $this->joinText([$position->title, $position->company_name]),
                body: $position->description,
                occurredAt: $position->started_on,
                metadata: ['location' => $position->location],
            );
        }

        foreach (LinkedinProject::query()->lazyById() as $project) {
            yield new SearchDocumentData(
                sourceType: 'linkedin',
                sourceTable: 'linkedin_projects',
                sourceId: $project->canonical_key,
                title: $project->title,
                body: $project->description,
                occurredAt: $project->started_on,
                urlOrLocator: $project->url,
            );
        }

        foreach (LinkedinRichMedia::query()->lazyById() as $media) {
            yield new SearchDocumentData(
                sourceType: 'linkedin',
                sourceTable: 'linkedin_rich_media',
                sourceId: $media->canonical_key,
                title: null,
                body: $media->media_description,
                occurredAt: $media->observed_at,
                urlOrLocator: $media->media_link,
            );
        }
    }
}
