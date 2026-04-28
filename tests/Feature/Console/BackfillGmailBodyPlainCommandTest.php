<?php

declare(strict_types=1);

use App\Models\GmailAccount;
use App\Models\GmailMessage;
use App\Services\Gmail\GmailHtmlBodyTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('dry runs without writing body plain values', function (): void {
    insertGmailBackfillMessage('msg-001', null, '<p>Hello <strong>Odinn</strong></p>');
    insertGmailBackfillMessage('msg-empty', null, '<div><br></div>');

    artisanCommand($this, 'gmail:backfill-body-plain', ['--dry-run' => true])
        ->expectsOutputToContain('Candidates: 2')
        ->expectsOutputToContain('Processed: 2/2; would update: 1; would write empty: 1; failed conversions: 0.')
        ->expectsOutputToContain('Would update: 1')
        ->expectsOutputToContain('Would write empty: 1')
        ->expectsOutputToContain('Failed conversions: 0')
        ->expectsOutputToContain('Dry run: rendered candidates but wrote no rows.')
        ->assertSuccessful();

    expect(gmailBackfillPlainText('msg-001'))->toBeNull();
    expect(gmailBackfillPlainText('msg-empty'))->toBeNull();
});

it('fills only null plain bodies and does not overwrite existing text', function (): void {
    insertGmailBackfillMessage('msg-missing', null, '<p>Rendered <strong>text</strong></p>');
    insertGmailBackfillMessage('msg-empty', null, '<div><br></div>');
    insertGmailBackfillMessage('msg-blank', " \n ", '<p>Already processed as blank</p>');
    insertGmailBackfillMessage('msg-existing', 'Original plain text', '<p>Do not use me</p>');
    insertGmailBackfillMessage('msg-no-html', null, null);

    artisanCommand($this, 'gmail:backfill-body-plain', ['--chunk' => 2])
        ->expectsOutputToContain('Candidates: 2')
        ->expectsOutputToContain('Processed: 2/2; updated: 2.')
        ->expectsOutputToContain('Updated: 2')
        ->assertSuccessful();

    expect(gmailBackfillPlainText('msg-missing'))
        ->toContain('Rendered text')
        ->and(gmailBackfillPlainText('msg-empty'))
        ->toBe('')
        ->and(gmailBackfillPlainText('msg-blank'))
        ->toBe(" \n ")
        ->and(gmailBackfillPlainText('msg-existing'))
        ->toBe('Original plain text')
        ->and(gmailBackfillPlainText('msg-no-html'))
        ->toBeNull();
});

it('honors the limit option', function (): void {
    insertGmailBackfillMessage('msg-001', null, '<p>First</p>');
    insertGmailBackfillMessage('msg-002', null, '<p>Second</p>');

    artisanCommand($this, 'gmail:backfill-body-plain', ['--limit' => 1])
        ->expectsOutputToContain('Candidates: 1')
        ->expectsOutputToContain('Updated: 1')
        ->assertSuccessful();

    expect(GmailMessage::query()->whereNotNull('body_plain')->count())->toBe(1);
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

    expect(GmailMessage::query()->whereNotNull('body_plain')->count())->toBe(0);
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
        ->expectsOutputToContain('Would write empty: 0')
        ->expectsOutputToContain('Failed conversions: 1')
        ->expectsOutputToContain('Failed batch:')
        ->assertFailed();

    expect(GmailMessage::query()->whereNotNull('body_plain')->count())->toBe(0);
});

function insertGmailBackfillMessage(string $messageId, ?string $bodyPlain, ?string $bodyHtml): void
{
    $account = GmailAccount::query()->create([
        'account_key' => sha1($messageId),
        'account_email' => $messageId.'@example.com',
        'access_mode' => 'api',
    ]);

    $thread = $account->threads()->create([
        'thread_id' => 'thread-'.$messageId,
    ]);

    $thread->messages()->create([
        'message_id' => $messageId,
        'body_plain' => $bodyPlain,
        'body_html' => $bodyHtml,
        'raw_headers' => [],
        'raw_payload' => [],
    ]);
}

function gmailBackfillPlainText(string $messageId): ?string
{
    return GmailMessage::query()
        ->where('message_id', $messageId)
        ->firstOrFail()
        ->body_plain;
}
