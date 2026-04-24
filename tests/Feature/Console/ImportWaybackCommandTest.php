<?php

declare(strict_types=1);

use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/imports/wayback'));
    File::deleteDirectory(base_path('data/runs'));
    File::deleteDirectory(base_path('data/intake'));
});

it('dry runs wayback capture counts without writing importer state', function (): void {
    Http::fake([
        'https://web.archive.org/cdx?*' => Http::response([
            [
                'urlkey',
                'timestamp',
                'original',
                'mimetype',
                'statuscode',
                'digest',
                'length',
            ],
            [
                'dk,odinns)/',
                '20010202124700',
                'http://odinns.dk/',
                'text/html',
                '200',
                'digest-one',
                '1234',
            ],
            [
                'dk,odinns)/about',
                '20030102030405',
                'http://odinns.dk/about',
                'text/html',
                '200',
                'digest-two',
                '2345',
            ],
        ]),
    ]);

    $this->artisan('import:wayback', [
        'scope' => 'odinns.dk',
        '--dry-run' => true,
        '--delay-ms' => 0,
    ])
        ->expectsOutputToContain('Wayback dry run')
        ->expectsOutputToContain('Available CDX captures: 2')
        ->expectsOutputToContain('Would process with current limit: 2')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(0);
    expect(DB::table('wayback_scopes')->count())->toBe(0);
    expect(DB::table('wayback_captures')->count())->toBe(0);
    expect(DB::table('runs')->count())->toBe(0);
});

it('imports wayback captures from the cli with useful output', function (): void {
    Http::fake([
        'https://web.archive.org/cdx?*' => Http::response([
            [
                'urlkey',
                'timestamp',
                'original',
                'mimetype',
                'statuscode',
                'digest',
                'length',
            ],
            [
                'dk,odinns)/about',
                '20030102030405',
                'http://odinns.dk/about',
                'text/html',
                '200',
                'digest-one',
                '1234',
            ],
        ]),
        'https://web.archive.org/web/20030102030405id_/http://odinns.dk/about' => Http::response(
            '<html><head><title>About Odinn</title><meta name="description" content="Old personal site"></head><body><div id="wm-ipp">Wayback chrome</div><h1>About Odinn</h1><p>I built Goldware and wrote about old web projects.</p></body></html>'
        ),
    ]);

    $this->artisan('import:wayback', [
        'scope' => 'odinns.dk',
        '--delay-ms' => 0,
    ])
        ->expectsOutputToContain('Recording intake for Wayback scope')
        ->expectsOutputToContain('Importing Wayback captures')
        ->expectsOutputToContain('Import complete')
        ->assertSuccessful();

    expect(DB::table('intake_records')->count())->toBe(1);
    expect(DB::table('wayback_scopes')->count())->toBe(1);
    expect(DB::table('wayback_captures')->count())->toBe(1);
    expect(DB::table('runs')->where('status', Run::STATUS_SUCCEEDED)->count())->toBe(1);
    expect(DB::table('run_artifacts')->where('artifact_kind', 'wayback-import-summary')->count())->toBe(1);
    expect(DB::table('provenance_links')->where('claim_key', 'imported-wayback-biographical-capture')->count())->toBe(1);

    $run = DB::table('runs')->first();
    expect(File::exists(base_path('data/imports/wayback/wayback-import-summary-run-'.$run->id.'.json')))->toBeTrue();

    $capture = DB::table('wayback_captures')->first();
    expect($capture->title)->toBe('About Odinn');
    expect($capture->verdict)->toBe('accepted');
    expect($capture->extracted_authored_text)->toContain('Goldware');
    expect($capture->extracted_authored_text)->not->toContain('Wayback chrome');
});
