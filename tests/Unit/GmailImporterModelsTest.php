<?php

declare(strict_types=1);

use App\Models\GmailAccount;
use App\Models\GmailAttachment;
use App\Models\GmailMessage;
use App\Models\GmailMessageLabel;
use App\Models\GmailMessageObservation;
use App\Models\GmailSourceSet;
use App\Models\GmailThread;
use Carbon\CarbonImmutable;

it('maps gmail importer tables through explicit eloquent model contracts', function (): void {
    $sourceSet = new GmailSourceSet;
    $account = new GmailAccount;
    $thread = new GmailThread([
        'raw_thread' => ['id' => 'thread-001'],
    ]);
    $message = new GmailMessage([
        'raw_headers' => [['name' => 'Subject', 'value' => 'Hello']],
        'internal_date' => '1713862800123',
        'message_received_at' => '2024-04-23 09:00:00',
        'raw_payload' => ['mimeType' => 'multipart/alternative'],
    ]);
    $label = new GmailMessageLabel;
    $attachment = new GmailAttachment([
        'size_bytes' => '204800',
    ]);
    $observation = new GmailMessageObservation;

    expect($sourceSet->getTable())->toBe('gmail_source_sets')
        ->and($sourceSet->messageObservations()->getForeignKeyName())->toBe('gmail_source_set_id');

    expect($account->getTable())->toBe('gmail_accounts')
        ->and($account->threads()->getForeignKeyName())->toBe('gmail_account_id');

    expect($thread->getTable())->toBe('gmail_threads')
        ->and($thread->raw_thread)->toBeArray()
        ->and($thread->account()->getForeignKeyName())->toBe('gmail_account_id')
        ->and($thread->messages()->getForeignKeyName())->toBe('gmail_thread_id');

    expect($message->getTable())->toBe('gmail_messages')
        ->and($message->raw_headers)->toBeArray()
        ->and($message->internal_date)->toBe(1713862800123)
        ->and($message->message_received_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($message->raw_payload)->toBeArray()
        ->and($message->thread()->getForeignKeyName())->toBe('gmail_thread_id')
        ->and($message->labels()->getForeignKeyName())->toBe('gmail_message_id')
        ->and($message->attachments()->getForeignKeyName())->toBe('gmail_message_id')
        ->and($message->observations()->getForeignKeyName())->toBe('gmail_message_id');

    expect($label->getTable())->toBe('gmail_message_labels')
        ->and($label->message()->getForeignKeyName())->toBe('gmail_message_id');

    expect($attachment->getTable())->toBe('gmail_attachments')
        ->and($attachment->size_bytes)->toBe(204800)
        ->and($attachment->message()->getForeignKeyName())->toBe('gmail_message_id');

    expect($observation->getTable())->toBe('gmail_message_observations')
        ->and($observation->message()->getForeignKeyName())->toBe('gmail_message_id')
        ->and($observation->sourceSet()->getForeignKeyName())->toBe('gmail_source_set_id');
});
