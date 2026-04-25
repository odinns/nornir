<?php

declare(strict_types=1);

use App\Actions\Import\ImportWaybackAction;
use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Data\Intake\RecordIntakeResultData;
use App\Models\WaybackCapture;
use App\Services\Wayback\WaybackClient;
use App\Services\Wayback\WaybackMirrorDownloader;
use App\Services\Wayback\WaybackScreenshotter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/wayback'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('reruns without duplicating captures and refreshes current extraction', function (): void {
    $client = new FakeWaybackClient('<html><head><title>First title</title></head><body><p>Original biography text</p></body></html>');
    app()->instance(WaybackClient::class, $client);

    app(ImportWaybackAction::class)(makeWaybackIntake()->dispatchPayload);

    $client->html = '<html><head><title>Updated title</title></head><body><p>Updated biography text</p></body></html>';

    $result = app(ImportWaybackAction::class)(makeWaybackIntake()->dispatchPayload);

    expect(DB::table('wayback_captures')->count())->toBe(1);
    expect($result->summary['captures'])->toBe(1);

    $capture = WaybackCapture::firstOrFail();
    expect($capture->title)->toBe('Updated title');
    expect($capture->extracted_authored_text)->toContain('Updated biography text');
});

it('keeps separate scope rows for the same locator with different boundaries', function (): void {
    app()->instance(WaybackClient::class, new FakeWaybackClient);

    app(ImportWaybackAction::class)(makeWaybackIntake()->dispatchPayload);
    app(ImportWaybackAction::class)(makeWaybackIntake(matchMode: 'prefix')->dispatchPayload);

    expect(DB::table('wayback_scopes')->count())->toBe(2);
});

it('records replay fetch failures without aborting the import run', function (): void {
    app()->instance(WaybackClient::class, new class extends WaybackClient
    {
        public function cdxCaptures(string $scope, string $matchMode, ?string $from, ?string $to, int $limit, int $delayMs): array
        {
            unset($scope, $matchMode, $from, $to, $limit, $delayMs);

            return [
                [
                    'urlkey' => 'dk,odinns)/blocked',
                    'timestamp' => '20051024073048',
                    'original' => 'http://www.odinns.dk/blocked',
                    'mimetype' => 'text/html',
                    'statuscode' => '200',
                    'digest' => 'blocked-digest',
                    'length' => '1234',
                ],
                [
                    'urlkey' => 'dk,odinns)/about',
                    'timestamp' => '20060101000000',
                    'original' => 'http://www.odinns.dk/about',
                    'mimetype' => 'text/html',
                    'statuscode' => '200',
                    'digest' => 'ok-digest',
                    'length' => '2345',
                ],
            ];
        }

        public function replayHtml(string $timestamp, string $originalUrl, int $delayMs): string
        {
            unset($originalUrl, $delayMs);

            if ($timestamp === '20051024073048') {
                throw new RuntimeException('HTTP request returned status code 403');
            }

            return '<html><head><title>About Odinn</title></head><body><p>I built Goldware.</p></body></html>';
        }
    });

    $result = app(ImportWaybackAction::class)(makeWaybackIntake()->dispatchPayload);

    expect($result->run->status)->toBe('succeeded');
    expect($result->summary['captures'])->toBe(2);
    expect($result->summary['accepted'])->toBe(1);
    expect($result->summary['failed'])->toBe(1);
    expect(DB::table('wayback_captures')->count())->toBe(2);
    expect(DB::table('wayback_captures')->where('timestamp', '20051024073048')->value('verdict'))->toBe('failed');
    expect(DB::table('wayback_captures')->where('timestamp', '20051024073048')->value('reject_reason'))->toBe('replay-fetch-failed');
});

it('normalizes legacy replay html to utf-8 before storing it', function (): void {
    app()->instance(WaybackClient::class, new FakeWaybackClient("<html><head><title>Odinn S\xF8rensen</title></head><body><p>F\xF8dt i K\xF8benhavn.</p></body></html>"));

    app(ImportWaybackAction::class)(makeWaybackIntake()->dispatchPayload);

    $capture = WaybackCapture::firstOrFail();

    expect($capture->title)->toBe('Odinn Sørensen');
    expect($capture->raw_replay_html)->toContain('Sørensen');
    expect($capture->extracted_authored_text)->toContain('Født i København');
});

it('rejects default excluded counter cgi captures before replay fetch', function (): void {
    $client = new class extends WaybackClient
    {
        public int $replayCalls = 0;

        public function cdxCaptures(string $scope, string $matchMode, ?string $from, ?string $to, int $limit, int $delayMs): array
        {
            unset($scope, $matchMode, $from, $to, $limit, $delayMs);

            return [[
                'urlkey' => 'dk,odinns)/cgi-bin/nph-count',
                'timestamp' => '20011028141543',
                'original' => 'http://www.odinns.dk/cgi-bin/nph-count?width=5&link=odinns-20001020',
                'mimetype' => 'text/html',
                'statuscode' => '200',
                'digest' => 'counter-digest',
                'length' => '123',
            ]];
        }

        public function replayHtml(string $timestamp, string $originalUrl, int $delayMs): string
        {
            unset($timestamp, $originalUrl, $delayMs);
            $this->replayCalls++;

            return '<html><body>counter</body></html>';
        }
    };

    app()->instance(WaybackClient::class, $client);

    $result = app(ImportWaybackAction::class)(makeWaybackIntake()->dispatchPayload);

    expect($result->summary['captures'])->toBe(1);
    expect($result->summary['rejected'])->toBe(1);
    expect($client->replayCalls)->toBe(0);
    expect(DB::table('wayback_captures')->where('timestamp', '20011028141543')->value('verdict'))->toBe('rejected');
    expect(DB::table('wayback_captures')->where('timestamp', '20011028141543')->value('reject_reason'))->toBe('excluded-url');
});

it('hydrates screenshots and mirrors on later reruns without changing capture identity', function (): void {
    app()->instance(WaybackClient::class, new FakeWaybackClient);
    app(ImportWaybackAction::class)(makeWaybackIntake()->dispatchPayload);

    $screenshotter = new class extends WaybackScreenshotter
    {
        public int $calls = 0;

        public function capture(string $url, string $path): array
        {
            unset($url);
            $this->calls++;
            File::ensureDirectoryExists(dirname($path));
            File::put($path, 'png');

            return ['path' => $path, 'hash' => hash_file('sha256', $path) ?: ''];
        }
    };

    $mirrorDownloader = new class extends WaybackMirrorDownloader
    {
        public int $calls = 0;

        public function mirror(string $url, string $directory): string
        {
            unset($url);
            $this->calls++;
            File::ensureDirectoryExists($directory);
            File::put($directory.'/index.html', 'mirror');

            return $directory;
        }
    };

    app()->instance(WaybackScreenshotter::class, $screenshotter);
    app()->instance(WaybackMirrorDownloader::class, $mirrorDownloader);

    $hydrated = app(ImportWaybackAction::class)(makeWaybackIntake(withScreenshots: true, mirrorAssets: true)->dispatchPayload);

    $skipped = app(ImportWaybackAction::class)(makeWaybackIntake(withScreenshots: true, mirrorAssets: true)->dispatchPayload);

    expect(DB::table('wayback_captures')->count())->toBe(1);
    expect($hydrated->summary['screenshots'])->toBe(1);
    expect($hydrated->summary['mirrors'])->toBe(1);
    expect($skipped->summary['screenshots'])->toBe(0);
    expect($skipped->summary['mirrors'])->toBe(0);
    expect($screenshotter->calls)->toBe(1);
    expect($mirrorDownloader->calls)->toBe(1);
    expect(DB::table('provenance_links')->where('claim_key', 'hydrated-wayback-screenshot')->count())->toBe(1);
    expect(DB::table('provenance_links')->where('claim_key', 'hydrated-wayback-mirror')->count())->toBe(1);
});

it('does not abort an import when an optional screenshot fails', function (): void {
    app()->instance(WaybackClient::class, new FakeWaybackClient);
    app()->instance(WaybackScreenshotter::class, new class extends WaybackScreenshotter
    {
        public function capture(string $url, string $path): array
        {
            unset($url, $path);

            throw new RuntimeException('screenshot refused');
        }
    });

    $result = app(ImportWaybackAction::class)(makeWaybackIntake(withScreenshots: true)->dispatchPayload);

    expect($result->run->status)->toBe('succeeded');
    expect($result->summary['accepted'])->toBe(1);
    expect($result->summary['screenshots'])->toBe(0);
    expect(DB::table('wayback_captures')->count())->toBe(1);
    expect(DB::table('wayback_captures')->value('screenshot_path'))->toBeNull();
});

function makeWaybackIntake(bool $withScreenshots = false, bool $mirrorAssets = false, string $matchMode = 'host'): RecordIntakeResultData
{
    return app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'wayback',
        accessMode: 'web-api',
        sourceLocator: 'odinns.dk',
        scopeSnapshot: [
            'match_mode' => $matchMode,
            'limit' => 100,
        ],
        importerOptions: [
            'with_screenshots' => $withScreenshots,
            'mirror_assets' => $mirrorAssets,
            'delay_ms' => 0,
        ],
    ));
}

class FakeWaybackClient extends WaybackClient
{
    public function __construct(
        public string $html = '<html><head><title>About Odinn</title></head><body><p>I built Goldware.</p></body></html>',
    ) {}

    public function cdxCaptures(string $scope, string $matchMode, ?string $from, ?string $to, int $limit, int $delayMs): array
    {
        unset($scope, $matchMode, $from, $to, $limit, $delayMs);

        return [[
            'urlkey' => 'dk,odinns)/about',
            'timestamp' => '20030102030405',
            'original' => 'http://odinns.dk/about',
            'mimetype' => 'text/html',
            'statuscode' => '200',
            'digest' => 'digest-one',
            'length' => '1234',
        ]];
    }

    public function replayHtml(string $timestamp, string $originalUrl, int $delayMs): string
    {
        unset($timestamp, $originalUrl, $delayMs);

        return $this->html;
    }
}
