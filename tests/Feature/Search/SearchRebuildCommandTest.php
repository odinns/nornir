<?php

declare(strict_types=1);

use App\Models\AppleHealthRecord;
use App\Models\AppleMessagesConversation;
use App\Models\AppleMessagesMessage;
use App\Models\AppleMessagesParticipant;
use App\Models\ChatGptArchive;
use App\Models\ChatGptConversation;
use App\Models\ChatGptMessage;
use App\Models\ChatGptMessagePart;
use App\Models\ChatGptNode;
use App\Models\FacebookArchive;
use App\Models\FacebookMessage;
use App\Models\FacebookPerson;
use App\Models\FacebookPost;
use App\Models\FacebookThread;
use App\Models\FidonetMessage;
use App\Models\FidonetMessageCleanup;
use App\Models\FidonetSource;
use App\Models\GmailAccount;
use App\Models\GmailMessage;
use App\Models\GmailThread;
use App\Models\InstagramAccount;
use App\Models\InstagramPost;
use App\Models\LinkedinArchive;
use App\Models\LinkedinConversation;
use App\Models\LinkedinMessage;
use App\Models\LinkedinPerson;
use App\Models\MediaFile;
use App\Models\SearchDocument;
use App\Models\TwitterTweet;
use App\Models\WaybackCapture;
use App\Models\WaybackScope;
use App\Search\SearchIndexer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class FakeSearchIndexer implements SearchIndexer
{
    public int $flushCalls = 0;

    public int $importCalls = 0;

    public ?string $lastFlushedSourceType = null;

    public ?string $lastImportedSourceType = null;

    public function flush(?string $sourceType = null): void
    {
        $this->flushCalls++;
        $this->lastFlushedSourceType = $sourceType;
    }

    public function import(?string $sourceType = null): void
    {
        $this->importCalls++;
        $this->lastImportedSourceType = $sourceType;
    }
}

beforeEach(function (): void {
    app()->instance(SearchIndexer::class, new FakeSearchIndexer);
});

it('configures scout to use local meilisearch', function (): void {
    expect(config('scout.driver'))->toBe('meilisearch')
        ->and(config('scout.meilisearch.host'))->toBe('http://127.0.0.1:7700')
        ->and(config('scout.meilisearch.key'))->toBe('local-dev-key');
});

it('exposes the normalized searchable document shape', function (): void {
    $document = SearchDocument::withoutSyncingToSearch(static fn (): SearchDocument => SearchDocument::query()->create([
        'source_type' => 'gmail',
        'source_table' => 'gmail_messages',
        'source_id' => 'msg-1',
        'title' => 'Hello',
        'body' => 'Plain body only',
        'occurred_at' => CarbonImmutable::parse('2026-04-25 10:00:00'),
        'participants' => ['sender@example.com'],
        'url_or_locator' => 'gmail://msg-1',
        'metadata' => ['thread' => 'thread-1'],
    ]));

    expect($document->toSearchableArray())->toMatchArray([
        'id' => $document->id,
        'source_type' => 'gmail',
        'source_table' => 'gmail_messages',
        'source_id' => 'msg-1',
        'title' => 'Hello',
        'body' => 'Plain body only',
        'participants' => ['sender@example.com'],
        'url_or_locator' => 'gmail://msg-1',
        'metadata' => ['thread' => 'thread-1'],
    ]);
});

it('dry runs without writing projection rows', function (): void {
    createGmailSearchFixture('body from dry run');

    artisanCommand($this, 'search:rebuild', ['--dry-run' => true])
        ->expectsOutputToContain('gmail')
        ->expectsOutputToContain('Search rebuild dry run complete.')
        ->assertSuccessful();

    expect(SearchDocument::query()->count())->toBe(0);
});

it('limits rebuilds to one source and imports projection rows into Scout boundary', function (): void {
    createGmailSearchFixture('plain gmail body');
    createTwitterSearchFixture('tweet body');

    artisanCommand($this, 'search:rebuild', ['--source' => 'gmail'])
        ->expectsTable(['Source', 'Indexed', 'Skipped'], [['gmail', 1, 0]])
        ->expectsOutputToContain('Search rebuild complete.')
        ->assertSuccessful();

    expect(SearchDocument::query()->count())->toBe(1);

    $document = SearchDocument::query()->firstOrFail();
    expect($document->source_type)->toBe('gmail')
        ->and($document->body)->toBe('plain gmail body');

    /** @var FakeSearchIndexer $indexer */
    $indexer = app(SearchIndexer::class);
    expect($indexer->flushCalls)->toBe(1)
        ->and($indexer->importCalls)->toBe(1)
        ->and($indexer->lastFlushedSourceType)->toBe('gmail')
        ->and($indexer->lastImportedSourceType)->toBe('gmail');
});

it('skips empty records and reports counts per source', function (): void {
    createGmailSearchFixture('', false);

    artisanCommand($this, 'search:rebuild', ['--source' => 'gmail'])
        ->expectsOutputToContain('gmail')
        ->assertSuccessful();

    expect(SearchDocument::query()->count())->toBe(0);
});

it('builds representative search documents for imported source families', function (): void {
    createChatGptSearchFixture();
    createGmailSearchFixture('Gmail plain body');
    createAppleMessagesSearchFixture();
    createTwitterSearchFixture('Twitter full text');
    createLinkedinSearchFixture();
    createFacebookSearchFixture();
    createInstagramSearchFixture();
    createFidonetSearchFixture();
    createWaybackSearchFixture();
    createMediaSearchFixture();
    createAppleHealthSearchFixture();

    artisanCommand($this, 'search:rebuild')->assertSuccessful();

    expect(SearchDocument::query()->pluck('source_type')->all())->toEqualCanonicalizing([
        'chatgpt',
        'gmail',
        'apple-messages',
        'twitter',
        'linkedin',
        'facebook',
        'facebook',
        'instagram',
        'fidonet',
        'wayback',
        'media',
        'apple-health',
    ]);

    expect(SearchDocument::query()->where('source_type', 'gmail')->firstOrFail()->body)->toBe('Gmail plain body')
        ->and(SearchDocument::query()->where('source_type', 'chatgpt')->firstOrFail()->body)->toContain('ChatGPT body')
        ->and(SearchDocument::query()->where('source_type', 'fidonet')->firstOrFail()->body)->toBe('Clean Fidonet authored text')
        ->and(SearchDocument::query()->where('source_type', 'wayback')->firstOrFail()->body)->toContain('Wayback authored text');
});

it('can query projection rows through scout database engine smoke path', function (): void {
    config()->set('scout.driver', 'database');

    SearchDocument::query()->create([
        'source_type' => 'gmail',
        'source_table' => 'gmail_messages',
        'source_id' => 'msg-smoke',
        'title' => 'Needle subject',
        'body' => 'Search smoke needle body',
        'participants' => ['odinn@example.com'],
        'metadata' => [],
    ]);

    $results = SearchDocument::search('needle')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->source_id)->toBe('msg-smoke');
});

function createChatGptSearchFixture(): void
{
    $archive = ChatGptArchive::query()->create([
        'archive_key' => 'archive-1',
        'source_locator' => 'chatgpt.zip',
        'source_file' => 'conversations.json',
    ]);
    $conversation = ChatGptConversation::query()->create([
        'chatgpt_archive_id' => $archive->id,
        'conversation_id' => 'conversation-search',
        'title' => 'ChatGPT title',
        'conversation_created_at' => CarbonImmutable::parse('2024-01-01 10:00:00'),
    ]);
    $node = ChatGptNode::query()->create([
        'chatgpt_conversation_id' => $conversation->id,
        'node_id' => 'node-search',
    ]);
    $message = ChatGptMessage::query()->create([
        'chatgpt_conversation_id' => $conversation->id,
        'chatgpt_node_id' => $node->id,
        'message_id' => 'message-search',
        'author_role' => 'user',
        'message_created_at' => CarbonImmutable::parse('2024-01-01 10:01:00'),
    ]);
    ChatGptMessagePart::query()->create([
        'chatgpt_message_id' => $message->id,
        'part_index' => 0,
        'part_type' => 'text',
        'text_part' => 'ChatGPT body',
    ]);
}

function createGmailSearchFixture(string $body, bool $participants = true): void
{
    $account = GmailAccount::query()->create([
        'account_key' => sha1('gmail'),
        'account_email' => 'odinn@example.com',
    ]);
    $thread = GmailThread::query()->create([
        'gmail_account_id' => $account->id,
        'thread_id' => 'thread-search',
    ]);
    GmailMessage::query()->create([
        'gmail_thread_id' => $thread->id,
        'message_id' => 'msg-search',
        'from_header' => $participants ? 'sender@example.com' : null,
        'to_header' => $participants ? 'odinn@example.com' : null,
        'subject' => $body === '' ? '' : 'Gmail subject',
        'body_plain' => $body,
        'message_received_at' => CarbonImmutable::parse('2026-04-25 10:00:00'),
    ]);
}

function createAppleMessagesSearchFixture(): void
{
    $conversation = AppleMessagesConversation::query()->create(['conversation_key' => 'apple-conv']);
    $participant = AppleMessagesParticipant::query()->create(['identifier' => '+4511111111', 'display_name' => 'Apple Person']);
    $conversation->participants()->attach($participant->id);
    AppleMessagesMessage::query()->create([
        'apple_messages_conversation_id' => $conversation->id,
        'sender_participant_id' => $participant->id,
        'canonical_key' => 'apple-message',
        'sent_at' => CarbonImmutable::parse('2024-02-01 10:00:00'),
        'text_body' => 'Apple message body',
    ]);
}

function createTwitterSearchFixture(string $body): void
{
    TwitterTweet::query()->create([
        'tweet_id' => 'tweet-search',
        'source_surface' => 'tweet',
        'tweeted_at' => CarbonImmutable::parse('2024-03-01 10:00:00'),
        'full_text' => $body,
        'account_id' => 'twitter-account',
    ]);
}

function createLinkedinSearchFixture(): void
{
    $archive = LinkedinArchive::query()->create([
        'archive_key' => 'li-archive',
        'source_locator' => 'linkedin.zip',
        'access_mode' => 'local-path',
    ]);
    $person = LinkedinPerson::query()->create(['person_key' => 'person-1', 'display_name' => 'LinkedIn Sender']);
    $conversation = LinkedinConversation::query()->create([
        'first_seen_linkedin_archive_id' => $archive->id,
        'conversation_key' => 'li-conv',
        'source_conversation_id' => 'li-source',
        'title' => 'LinkedIn thread',
    ]);
    LinkedinMessage::query()->create([
        'linkedin_conversation_id' => $conversation->id,
        'first_seen_linkedin_archive_id' => $archive->id,
        'sender_linkedin_person_id' => $person->id,
        'canonical_key' => 'li-message',
        'subject' => 'LinkedIn subject',
        'content' => 'LinkedIn message body',
        'sent_at' => CarbonImmutable::parse('2024-04-01 10:00:00'),
    ]);
}

function createFacebookSearchFixture(): void
{
    $archive = FacebookArchive::query()->create([
        'source_key' => 'fb-archive',
        'source_locator' => 'facebook.zip',
        'access_mode' => 'local-path',
    ]);
    $thread = FacebookThread::query()->create([
        'facebook_archive_id' => $archive->id,
        'thread_key' => 'fb-thread',
        'category' => 'inbox',
        'title' => 'Facebook thread',
    ]);
    $person = FacebookPerson::query()->create(['person_key' => 'fb-person', 'display_name' => 'Facebook Sender']);
    $thread->participants()->attach($person->id);
    FacebookMessage::query()->create([
        'facebook_thread_id' => $thread->id,
        'sender_facebook_person_id' => $person->id,
        'canonical_key' => 'fb-message',
        'timestamp_ms' => 1_700_000_000_000,
        'sent_at' => CarbonImmutable::parse('2024-05-01 10:00:00'),
        'content' => 'Facebook message body',
    ]);
    FacebookPost::query()->create([
        'facebook_archive_id' => $archive->id,
        'canonical_key' => 'fb-post',
        'published_at' => CarbonImmutable::parse('2024-05-02 10:00:00'),
        'title' => 'Facebook post',
        'content' => 'Facebook post body',
    ]);
}

function createInstagramSearchFixture(): void
{
    $account = InstagramAccount::query()->create([
        'account_key' => sha1('instagram'),
        'username' => 'odinn',
    ]);
    InstagramPost::query()->create([
        'instagram_account_id' => $account->id,
        'post_key' => sha1('post'),
        'caption' => 'Instagram caption',
        'post_timestamp' => 1_700_000_000,
    ]);
}

function createFidonetSearchFixture(): void
{
    $source = FidonetSource::query()->create([
        'source_locator' => 'fidonet.sqlite',
        'scope_hash' => sha1('scope'),
        'access_mode' => 'db-connection',
        'driver' => 'sqlite',
        'database_name' => 'fidonet',
    ]);

    FidonetMessage::query()->create([
        'fidonet_source_id' => $source->id,
        'canonical_message_id' => 'fidonet-message',
        'area_code' => 'WINETDEV',
        'source_msgno' => 1,
        'subject' => 'Fidonet subject',
        'from_name' => 'Odinn',
        'to_name' => 'Bo',
        'posted_at' => CarbonImmutable::parse('1995-09-02 10:00:00'),
    ]);
    FidonetMessageCleanup::query()->create([
        'canonical_message_id' => 'fidonet-message',
        'cleaned_authored_text' => 'Clean Fidonet authored text',
        'cleanup_version' => 'test',
    ]);
}

function createWaybackSearchFixture(): void
{
    $scope = WaybackScope::query()->create([
        'scope' => 'https://example.com',
        'match_mode' => 'host',
        'filter_policy' => [],
        'source_key' => 'wayback-source',
    ]);
    WaybackCapture::query()->create([
        'wayback_scope_id' => $scope->id,
        'timestamp' => '20200101000000',
        'captured_at' => CarbonImmutable::parse('2020-01-01 00:00:00'),
        'original_url' => 'https://example.com/page',
        'original_url_hash' => sha1('https://example.com/page'),
        'replay_url' => 'https://web.archive.org/example',
        'cdx_fields' => [],
        'page_key' => 'page-key',
        'verdict' => 'accepted',
        'extracted_authored_text' => 'Wayback authored text',
        'title' => 'Wayback title',
        'retrieval_metadata' => [],
        'raw_cdx_json' => [],
    ]);
}

function createMediaSearchFixture(): void
{
    MediaFile::query()->create([
        'source_file_id' => 123,
        'volume_label' => 'LIMA-1',
        'directory_full_path' => '/Volumes/LIMA-1/Pictures',
        'event_label' => 'Birthday',
        'basename' => 'IMG_0001.jpg',
        'extension' => 'jpg',
        'normalized_file_type' => 'image',
    ]);
}

function createAppleHealthSearchFixture(): void
{
    AppleHealthRecord::query()->create([
        'canonical_key' => 'health-record',
        'record_type' => 'HKQuantityTypeIdentifierBodyMass',
        'source_name' => 'Health',
        'unit' => 'kg',
        'value' => '80',
        'start_at' => CarbonImmutable::parse('2024-06-01 10:00:00'),
    ]);
}
