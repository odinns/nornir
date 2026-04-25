<?php

declare(strict_types=1);

use App\Services\Gmail\GmailHtmlBodyTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('dry runs without writing body plain values', function (): void {
    insertGmailBackfillMessage('msg-001', null, '<p>Hello <strong>Odinn</strong></p>');
    insertGmailBackfillMessage('msg-empty', null, '<div><br></div>');

    artisanCommand($this, 'gmail:backfill-body-plain', ['--dry-run' => true])
        ->expectsOutputToContain('Candidates: 2')
        ->expectsOutputToContain('Would update: 1')
        ->expectsOutputToContain('Would skip empty: 1')
        ->expectsOutputToContain('Failed conversions: 0')
        ->expectsOutputToContain('Dry run: rendered candidates but wrote no rows.')
        ->assertSuccessful();

    expect(DB::table('gmail_messages')->where('message_id', 'msg-001')->value('body_plain'))->toBeNull();
    expect(DB::table('gmail_messages')->where('message_id', 'msg-empty')->value('body_plain'))->toBeNull();
});

it('fills only missing plain bodies and does not overwrite existing text', function (): void {
    insertGmailBackfillMessage('msg-missing', null, '<p>Rendered <strong>text</strong></p>');
    insertGmailBackfillMessage('msg-blank', " \n ", '<p>Blank becomes useful</p>');
    insertGmailBackfillMessage('msg-existing', 'Original plain text', '<p>Do not use me</p>');
    insertGmailBackfillMessage('msg-no-html', null, null);

    artisanCommand($this, 'gmail:backfill-body-plain', ['--chunk' => 2])
        ->expectsOutputToContain('Candidates: 2')
        ->expectsOutputToContain('Updated: 2')
        ->assertSuccessful();

    expect(DB::table('gmail_messages')->where('message_id', 'msg-missing')->value('body_plain'))
        ->toContain('Rendered text')
        ->and(DB::table('gmail_messages')->where('message_id', 'msg-blank')->value('body_plain'))
        ->toContain('Blank becomes useful')
        ->and(DB::table('gmail_messages')->where('message_id', 'msg-existing')->value('body_plain'))
        ->toBe('Original plain text')
        ->and(DB::table('gmail_messages')->where('message_id', 'msg-no-html')->value('body_plain'))
        ->toBeNull();
});

it('honors the limit option', function (): void {
    insertGmailBackfillMessage('msg-001', null, '<p>First</p>');
    insertGmailBackfillMessage('msg-002', null, '<p>Second</p>');

    artisanCommand($this, 'gmail:backfill-body-plain', ['--limit' => 1])
        ->expectsOutputToContain('Candidates: 1')
        ->expectsOutputToContain('Updated: 1')
        ->assertSuccessful();

    expect(DB::table('gmail_messages')->whereNotNull('body_plain')->count())->toBe(1);
});

it('rolls back a failed batch and stops', function (): void {
    insertGmailBackfillMessage('msg-ok', null, '<p>Safe text</p>');
    insertGmailBackfillMessage('msg-fail', null, '<p>Explode text</p>');

    app()->bind(GmailHtmlBodyTextExtractor::class, static fn (): GmailHtmlBodyTextExtractor => new class extends GmailHtmlBodyTextExtractor
    {
        public function extract(?string $html): ?string
        {
            if ($html !== null && str_contains($html, 'Explode')) {
                throw new RuntimeException('Extractor failed.');
            }

            return parent::extract($html);
        }
    });

    artisanCommand($this, 'gmail:backfill-body-plain', ['--chunk' => 2])
        ->expectsOutputToContain('Failed batch:')
        ->assertFailed();

    expect(DB::table('gmail_messages')->whereNotNull('body_plain')->count())->toBe(0);
});

it('dry run reports conversion failures and writes nothing', function (): void {
    insertGmailBackfillMessage('msg-ok', null, '<p>Safe text</p>');
    insertGmailBackfillMessage('msg-fail', null, '<p>Explode text</p>');

    app()->bind(GmailHtmlBodyTextExtractor::class, static fn (): GmailHtmlBodyTextExtractor => new class extends GmailHtmlBodyTextExtractor
    {
        public function extract(?string $html): ?string
        {
            if ($html !== null && str_contains($html, 'Explode')) {
                throw new RuntimeException('Extractor failed.');
            }

            return parent::extract($html);
        }
    });

    artisanCommand($this, 'gmail:backfill-body-plain', ['--dry-run' => true, '--chunk' => 2])
        ->expectsOutputToContain('Candidates: 2')
        ->expectsOutputToContain('Would update: 1')
        ->expectsOutputToContain('Would skip empty: 0')
        ->expectsOutputToContain('Failed conversions: 1')
        ->expectsOutputToContain('Failed batch:')
        ->assertFailed();

    expect(DB::table('gmail_messages')->whereNotNull('body_plain')->count())->toBe(0);
});

function insertGmailBackfillMessage(string $messageId, ?string $bodyPlain, ?string $bodyHtml): void
{
    $accountId = DB::table('gmail_accounts')->insertGetId([
        'account_key' => sha1($messageId),
        'account_email' => $messageId.'@example.com',
        'access_mode' => 'api',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $threadId = DB::table('gmail_threads')->insertGetId([
        'gmail_account_id' => $accountId,
        'thread_id' => 'thread-'.$messageId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('gmail_messages')->insert([
        'gmail_thread_id' => $threadId,
        'message_id' => $messageId,
        'body_plain' => $bodyPlain,
        'body_html' => $bodyHtml,
        'raw_headers' => json_encode([], JSON_THROW_ON_ERROR),
        'raw_payload' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
