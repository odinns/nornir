<?php

declare(strict_types=1);

use App\Models\FacebookArchive;
use App\Models\FacebookAttachment;
use App\Models\FacebookComment;
use App\Models\FacebookCommentObservation;
use App\Models\FacebookMessage;
use App\Models\FacebookMessageObservation;
use App\Models\FacebookMessageReaction;
use App\Models\FacebookPerson;
use App\Models\FacebookPost;
use App\Models\FacebookPostObservation;
use App\Models\FacebookProfileSnapshot;
use App\Models\FacebookReaction;
use App\Models\FacebookReactionObservation;
use App\Models\FacebookSocialEdge;
use App\Models\FacebookThread;
use Carbon\CarbonImmutable;

it('maps facebook importer tables through explicit eloquent model contracts', function (): void {
    $archive = new FacebookArchive;
    $person = new FacebookPerson;
    $profileSnapshot = new FacebookProfileSnapshot([
        'emails_json' => ['odinn@example.com'],
        'raw_profile' => ['name' => 'Odinn'],
    ]);
    $socialEdge = new FacebookSocialEdge([
        'observed_at' => '2026-04-24 08:30:00',
    ]);
    $thread = new FacebookThread([
        'is_still_participant' => 1,
        'message_count' => '2',
        'first_message_at' => '2026-04-24 08:30:00',
        'last_message_at' => '2026-04-24 08:35:00',
        'raw_thread' => ['title' => 'Thread'],
    ]);
    $post = new FacebookPost([
        'published_timestamp' => '1713947400',
        'published_at' => '2026-04-24 08:30:00',
        'raw_post' => ['title' => 'Post'],
    ]);
    $comment = new FacebookComment([
        'published_timestamp' => '1713947401',
        'published_at' => '2026-04-24 08:31:00',
        'raw_comment' => ['title' => 'Comment'],
    ]);
    $reaction = new FacebookReaction([
        'published_timestamp' => '1713947402',
        'published_at' => '2026-04-24 08:32:00',
        'raw_reaction' => ['reaction' => 'LIKE'],
    ]);
    $message = new FacebookMessage([
        'timestamp_ms' => '1713947400000',
        'sent_at' => '2026-04-24 08:30:00',
        'is_unsent' => 0,
        'raw_message' => ['content' => 'Hello'],
    ]);
    $messageObservation = new FacebookMessageObservation;
    $postObservation = new FacebookPostObservation;
    $commentObservation = new FacebookCommentObservation;
    $reactionObservation = new FacebookReactionObservation;
    $messageReaction = new FacebookMessageReaction;
    $attachment = new FacebookAttachment([
        'created_timestamp' => '1713947400',
        'file_size_bytes' => '204800',
        'raw_attachment' => ['uri' => 'messages/photo.jpg'],
    ]);

    expect($archive->getTable())->toBe('facebook_archives')
        ->and($archive->profileSnapshot()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($archive->threads()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($archive->posts()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($archive->comments()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($archive->reactions()->getForeignKeyName())->toBe('facebook_archive_id');

    expect($person->getTable())->toBe('facebook_people')
        ->and($person->profileSnapshots()->getForeignKeyName())->toBe('facebook_person_id')
        ->and($person->socialEdges()->getForeignKeyName())->toBe('facebook_person_id');

    expect($profileSnapshot->getTable())->toBe('facebook_profile_snapshots')
        ->and($profileSnapshot->emails_json)->toBeArray()
        ->and($profileSnapshot->raw_profile)->toBeArray()
        ->and($profileSnapshot->archive()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($profileSnapshot->person()->getForeignKeyName())->toBe('facebook_person_id');

    expect($socialEdge->getTable())->toBe('facebook_social_edges')
        ->and($socialEdge->observed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($socialEdge->archive()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($socialEdge->person()->getForeignKeyName())->toBe('facebook_person_id');

    expect($thread->getTable())->toBe('facebook_threads')
        ->and($thread->is_still_participant)->toBeTrue()
        ->and($thread->message_count)->toBe(2)
        ->and($thread->first_message_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($thread->last_message_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($thread->raw_thread)->toBeArray()
        ->and($thread->archive()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($thread->messages()->getForeignKeyName())->toBe('facebook_thread_id')
        ->and($thread->participants()->getForeignPivotKeyName())->toBe('facebook_thread_id')
        ->and($thread->participants()->getRelatedPivotKeyName())->toBe('facebook_person_id');

    expect($post->getTable())->toBe('facebook_posts')
        ->and($post->published_timestamp)->toBe(1713947400)
        ->and($post->published_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($post->raw_post)->toBeArray()
        ->and($post->archive()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($post->observations()->getForeignKeyName())->toBe('facebook_post_id')
        ->and($post->attachments()->getForeignKeyName())->toBe('facebook_post_id');

    expect($comment->getTable())->toBe('facebook_comments')
        ->and($comment->published_timestamp)->toBe(1713947401)
        ->and($comment->published_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($comment->raw_comment)->toBeArray()
        ->and($comment->archive()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($comment->observations()->getForeignKeyName())->toBe('facebook_comment_id');

    expect($reaction->getTable())->toBe('facebook_reactions')
        ->and($reaction->published_timestamp)->toBe(1713947402)
        ->and($reaction->published_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($reaction->raw_reaction)->toBeArray()
        ->and($reaction->archive()->getForeignKeyName())->toBe('facebook_archive_id')
        ->and($reaction->person()->getForeignKeyName())->toBe('facebook_person_id')
        ->and($reaction->observations()->getForeignKeyName())->toBe('facebook_reaction_id');

    expect($message->getTable())->toBe('facebook_messages')
        ->and($message->timestamp_ms)->toBe(1713947400000)
        ->and($message->sent_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->is_unsent)->toBeFalse()
        ->and($message->raw_message)->toBeArray()
        ->and($message->thread()->getForeignKeyName())->toBe('facebook_thread_id')
        ->and($message->sender()->getForeignKeyName())->toBe('sender_facebook_person_id')
        ->and($message->observations()->getForeignKeyName())->toBe('facebook_message_id')
        ->and($message->messageReactions()->getForeignKeyName())->toBe('facebook_message_id')
        ->and($message->attachments()->getForeignKeyName())->toBe('facebook_message_id');

    expect($messageObservation->getTable())->toBe('facebook_message_observations')
        ->and($messageObservation->message()->getForeignKeyName())->toBe('facebook_message_id')
        ->and($messageObservation->archive()->getForeignKeyName())->toBe('facebook_archive_id');

    expect($postObservation->getTable())->toBe('facebook_post_observations')
        ->and($postObservation->post()->getForeignKeyName())->toBe('facebook_post_id')
        ->and($postObservation->archive()->getForeignKeyName())->toBe('facebook_archive_id');

    expect($commentObservation->getTable())->toBe('facebook_comment_observations')
        ->and($commentObservation->comment()->getForeignKeyName())->toBe('facebook_comment_id')
        ->and($commentObservation->archive()->getForeignKeyName())->toBe('facebook_archive_id');

    expect($reactionObservation->getTable())->toBe('facebook_reaction_observations')
        ->and($reactionObservation->reaction()->getForeignKeyName())->toBe('facebook_reaction_id')
        ->and($reactionObservation->archive()->getForeignKeyName())->toBe('facebook_archive_id');

    expect($messageReaction->getTable())->toBe('facebook_message_reactions')
        ->and($messageReaction->message()->getForeignKeyName())->toBe('facebook_message_id')
        ->and($messageReaction->person()->getForeignKeyName())->toBe('facebook_person_id');

    expect($attachment->getTable())->toBe('facebook_attachments')
        ->and($attachment->created_timestamp)->toBe(1713947400)
        ->and($attachment->file_size_bytes)->toBe(204800)
        ->and($attachment->raw_attachment)->toBeArray()
        ->and($attachment->message()->getForeignKeyName())->toBe('facebook_message_id')
        ->and($attachment->post()->getForeignKeyName())->toBe('facebook_post_id');
});
