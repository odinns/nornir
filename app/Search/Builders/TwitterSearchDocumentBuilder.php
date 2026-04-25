<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\TwitterNoteTweet;
use App\Models\TwitterProfileSnapshot;
use App\Models\TwitterTweet;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class TwitterSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'twitter';
    }

    public function build(): iterable
    {
        foreach (TwitterTweet::query()->lazyById() as $tweet) {
            yield new SearchDocumentData(
                sourceType: 'twitter',
                sourceTable: 'twitter_tweets',
                sourceId: $tweet->tweet_id,
                title: null,
                body: $tweet->full_text,
                occurredAt: $tweet->tweeted_at,
                participants: $this->participants([$tweet->account_id, $tweet->in_reply_to_user_id]),
                metadata: ['conversation_id' => $tweet->conversation_id, 'lang' => $tweet->lang],
            );
        }

        foreach (TwitterNoteTweet::query()->lazyById() as $noteTweet) {
            yield new SearchDocumentData(
                sourceType: 'twitter',
                sourceTable: 'twitter_note_tweets',
                sourceId: $noteTweet->note_tweet_id,
                title: null,
                body: $noteTweet->full_text,
                occurredAt: $noteTweet->tweeted_at,
                participants: $this->participants([$noteTweet->account_id]),
            );
        }

        foreach (TwitterProfileSnapshot::query()->lazyById() as $profile) {
            yield new SearchDocumentData(
                sourceType: 'twitter',
                sourceTable: 'twitter_profile_snapshots',
                sourceId: (string) $profile->id,
                title: $profile->display_name ?? $profile->screen_name,
                body: $profile->bio,
                occurredAt: null,
                participants: $this->participants([$profile->display_name, $profile->screen_name]),
                urlOrLocator: $profile->website_url,
                metadata: ['location' => $profile->location],
            );
        }
    }
}
