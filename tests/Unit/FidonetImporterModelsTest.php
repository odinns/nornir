<?php

declare(strict_types=1);

use App\Models\FidonetArea;
use App\Models\FidonetAreaObservation;
use App\Models\FidonetMessage;
use App\Models\FidonetMessageCleanup;
use App\Models\FidonetMessageObservation;
use App\Models\FidonetMessageParticipant;
use App\Models\FidonetParticipant;
use App\Models\FidonetSource;
use App\Models\FidonetThread;
use App\Models\FidonetThreadMessage;
use App\Models\FidonetThreadObservation;
use Carbon\CarbonImmutable;

it('maps fidonet importer tables through explicit eloquent model contracts', function (): void {
    $source = new FidonetSource([
        'scope_snapshot' => ['areas' => ['TEST']],
    ]);
    $area = new FidonetArea;
    $message = new FidonetMessage([
        'posted_at' => '2026-04-24 08:30:00',
        'arrived_at' => '2026-04-24 08:31:00',
        'raw_metadata_json' => ['msgid' => '1:234/56'],
    ]);
    $participant = new FidonetParticipant([
        'is_odinn_candidate' => 1,
    ]);
    $messageParticipant = new FidonetMessageParticipant;
    $thread = new FidonetThread([
        'message_count' => '3',
        'is_synthetic' => 1,
    ]);
    $threadMessage = new FidonetThreadMessage([
        'thread_order' => '2',
    ]);
    $cleanup = new FidonetMessageCleanup([
        'is_test_like' => 0,
    ]);
    $areaObservation = new FidonetAreaObservation;
    $threadObservation = new FidonetThreadObservation;
    $messageObservation = new FidonetMessageObservation;

    expect($source->getTable())->toBe('fidonet_sources')
        ->and($source->scope_snapshot)->toBeArray()
        ->and($source->areas()->getForeignKeyName())->toBe('fidonet_source_id')
        ->and($source->messages()->getForeignKeyName())->toBe('fidonet_source_id')
        ->and($source->areaObservations()->getForeignKeyName())->toBe('fidonet_source_id')
        ->and($source->threadObservations()->getForeignKeyName())->toBe('fidonet_source_id')
        ->and($source->messageObservations()->getForeignKeyName())->toBe('fidonet_source_id');

    expect($area->getTable())->toBe('fidonet_areas')
        ->and($area->source()->getForeignKeyName())->toBe('fidonet_source_id');

    expect($message->getTable())->toBe('fidonet_messages')
        ->and($message->posted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->arrived_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->raw_metadata_json)->toBeArray()
        ->and($message->source()->getForeignKeyName())->toBe('fidonet_source_id')
        ->and($message->cleanup()->getForeignKeyName())->toBe('canonical_message_id');

    expect($participant->getTable())->toBe('fidonet_participants')
        ->and($participant->is_odinn_candidate)->toBeTrue()
        ->and($participant->messageParticipants()->getForeignKeyName())->toBe('fidonet_participant_id');

    expect($messageParticipant->getTable())->toBe('fidonet_message_participants')
        ->and($messageParticipant->participant()->getForeignKeyName())->toBe('fidonet_participant_id');

    expect($thread->getTable())->toBe('fidonet_threads')
        ->and($thread->message_count)->toBe(3)
        ->and($thread->is_synthetic)->toBeTrue()
        ->and($thread->threadMessages()->getForeignKeyName())->toBe('fidonet_thread_id');

    expect($threadMessage->getTable())->toBe('fidonet_thread_messages')
        ->and($threadMessage->thread_order)->toBe(2)
        ->and($threadMessage->thread()->getForeignKeyName())->toBe('fidonet_thread_id');

    expect($cleanup->getTable())->toBe('fidonet_message_cleanup')
        ->and($cleanup->is_test_like)->toBeFalse();

    expect($areaObservation->getTable())->toBe('fidonet_area_observations')
        ->and($areaObservation->source()->getForeignKeyName())->toBe('fidonet_source_id');

    expect($threadObservation->getTable())->toBe('fidonet_thread_observations')
        ->and($threadObservation->source()->getForeignKeyName())->toBe('fidonet_source_id')
        ->and($threadObservation->thread()->getForeignKeyName())->toBe('fidonet_thread_id');

    expect($messageObservation->getTable())->toBe('fidonet_message_observations')
        ->and($messageObservation->source()->getForeignKeyName())->toBe('fidonet_source_id');
});
