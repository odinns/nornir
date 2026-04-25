<?php

declare(strict_types=1);

use App\Actions\Intake\RecordIntakeAction;
use App\Data\Intake\RecordIntakeData;
use App\Models\IntakeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(base_path('data/intake'));
});

it('records an archive intake and emits an importer handoff payload', function (): void {
    $archivePath = makeTempFile('chatgpt-export.zip');

    $result = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'archive',
        sourceLocator: $archivePath,
        scopeSnapshot: [
            'archive_label' => 'chatgpt-export-2026-04-10',
        ],
        importerOptions: [
            'dry_run' => false,
        ],
    ));

    expect(IntakeRecord::query()->count())->toBe(1);
    expect($result->intakeRecord->source_type)->toBe('chatgpt');
    expect($result->intakeRecord->access_mode)->toBe('archive');
    expect($result->dispatchPayload->sourceType)->toBe('chatgpt');
    expect($result->dispatchPayload->accessMode)->toBe('archive');
    expect($result->dispatchPayload->sourceLocator)->toBe($archivePath);
    expect($result->dispatchPayload->importerKey)->toBe('chatgpt');
    expect($result->dispatchPayload->scopeSnapshot)->toBe([
        'archive_label' => 'chatgpt-export-2026-04-10',
    ]);
    expect($result->dispatchPayload->importerOptions)->toBe([
        'dry_run' => false,
    ]);
    expect(File::exists($result->reviewManifestPath))->toBeTrue();
});

it('records a local path intake with an explicit list of bounded roots', function (): void {
    $primaryRoot = makeTempDirectory('llm-wiki-chatgpt-primary');
    $secondaryRoot = makeTempDirectory('llm-wiki-chatgpt-secondary');

    $result = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $primaryRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$primaryRoot, $secondaryRoot],
            'relative_glob' => 'conversations/**/*.json',
        ],
        importerOptions: [],
    ));

    expect($result->intakeRecord->scope_snapshot)->toBe([
        'accepted_root_paths' => [$primaryRoot, $secondaryRoot],
        'relative_glob' => 'conversations/**/*.json',
    ]);
    /** @var array{accepted_root_paths:list<string>} $scopeSnapshot */
    $scopeSnapshot = $result->dispatchPayload->scopeSnapshot;
    expect($scopeSnapshot['accepted_root_paths'])->toBe([
        $primaryRoot,
        $secondaryRoot,
    ]);
});

it('normalizes persisted filesystem paths to absolute paths', function (): void {
    $primaryRoot = makeTempDirectory('llm-wiki-chatgpt-relative-primary');
    $secondaryRoot = makeTempDirectory('llm-wiki-chatgpt-relative-secondary');

    $relativePrimaryRoot = relativeToBasePath($primaryRoot);
    $relativeSecondaryRoot = relativeToBasePath($secondaryRoot);

    $result = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'local-path',
        sourceLocator: $relativePrimaryRoot,
        scopeSnapshot: [
            'accepted_root_paths' => [$relativePrimaryRoot, $relativeSecondaryRoot],
            'relative_glob' => 'conversations-*.json',
        ],
        importerOptions: [],
    ));

    $manifest = json_decode((string) file_get_contents($result->reviewManifestPath), true, 512, JSON_THROW_ON_ERROR);

    expect($result->intakeRecord->source_locator)->toBe($primaryRoot);
    expect($result->dispatchPayload->sourceLocator)->toBe($primaryRoot);
    /** @var array{accepted_root_paths:list<string>} $intakeScopeSnapshot */
    $intakeScopeSnapshot = $result->intakeRecord->scope_snapshot;
    expect($intakeScopeSnapshot['accepted_root_paths'])->toBe([
        $primaryRoot,
        $secondaryRoot,
    ]);
    /** @var array{accepted_root_paths:list<string>} $dispatchScopeSnapshot */
    $dispatchScopeSnapshot = $result->dispatchPayload->scopeSnapshot;
    expect($dispatchScopeSnapshot['accepted_root_paths'])->toBe([
        $primaryRoot,
        $secondaryRoot,
    ]);
    expect($manifest['requested']['source_locator'])->toBe($primaryRoot);
    expect($manifest['boundary']['source_locator'])->toBe($primaryRoot);
    expect($manifest['boundary']['scope_snapshot']['accepted_root_paths'])->toBe([
        $primaryRoot,
        $secondaryRoot,
    ]);
});

it('rejects unsupported source types', function (): void {
    $path = makeTempDirectory('unsupported-source');

    expect(fn () => app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'carrier-pigeon',
        accessMode: 'local-path',
        sourceLocator: $path,
        scopeSnapshot: [],
        importerOptions: [],
    )))->toThrow(InvalidArgumentException::class, 'Unsupported source type [carrier-pigeon].');
});

it('rejects unreachable source locators', function (): void {
    $missingArchive = base_path('data/tmp/does-not-exist/chatgpt-export.zip');

    expect(fn () => app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'archive',
        sourceLocator: $missingArchive,
        scopeSnapshot: [],
        importerOptions: [],
    )))->toThrow(InvalidArgumentException::class, 'Source locator is not reachable');
});

it('writes a review manifest that makes the boundary obvious', function (): void {
    $archivePath = makeTempFile('chatgpt-export.zip');

    $result = app(RecordIntakeAction::class)(new RecordIntakeData(
        sourceType: 'chatgpt',
        accessMode: 'archive',
        sourceLocator: $archivePath,
        scopeSnapshot: [
            'archive_label' => 'chatgpt-export-2026-04-10',
        ],
        importerOptions: [],
    ));

    $manifest = json_decode((string) file_get_contents($result->reviewManifestPath), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['requested']['source_type'])->toBe('chatgpt');
    expect($manifest['requested']['access_mode'])->toBe('archive');
    expect($manifest['requested']['source_locator'])->toBe($archivePath);
    expect($manifest['boundary']['source_locator'])->toBe($archivePath);
    expect($manifest['boundary']['scope_snapshot'])->toBe([
        'archive_label' => 'chatgpt-export-2026-04-10',
    ]);
    expect($manifest['handoff']['importer_key'])->toBe('chatgpt');
});

function makeTempFile(string $name): string
{
    $directory = makeTempDirectory(pathinfo($name, PATHINFO_FILENAME));
    $path = $directory.DIRECTORY_SEPARATOR.$name;

    file_put_contents($path, 'placeholder');

    return $path;
}

function makeTempDirectory(string $name): string
{
    $path = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));

    File::ensureDirectoryExists($path);

    return $path;
}

function relativeToBasePath(string $path): string
{
    return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
}
