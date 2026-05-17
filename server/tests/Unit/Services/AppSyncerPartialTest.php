<?php

declare(strict_types=1);

use App\Connectors\GooglePlayConnector;
use App\Connectors\ITunesLookupConnector;
use App\Models\App;
use App\Models\AppMetric;
use App\Models\AppVersion;
use App\Models\StoreListing;
use App\Models\StoreListingChange;
use App\Services\AppSyncer;
use App\Services\StoreCategoryResolver;

/**
 * Build an AppSyncer with mocked connectors. The methods under test in this
 * file never touch the connectors, so simple Mockery stubs are enough.
 */
function makeSyncer(): AppSyncer
{
    return new AppSyncer(
        Mockery::mock(ITunesLookupConnector::class),
        Mockery::mock(GooglePlayConnector::class),
        new StoreCategoryResolver,
    );
}

beforeEach(function () {
    $this->syncer = makeSyncer();
});

// ─── saveVersion ──────────────────────────────────────────────────

it('saveVersion returns null when the identity payload has no version string', function () {
    $app = App::factory()->create();

    $version = $this->syncer->saveVersion($app, []);

    expect($version)->toBeNull();
    expect(AppVersion::where('app_id', $app->id)->count())->toBe(0);
});

it('saveVersion creates an AppVersion row when a version string is present', function () {
    $app = App::factory()->create();

    $version = $this->syncer->saveVersion($app, [
        'version' => '1.2.3',
        'current_version_release_date' => '2026-05-01',
    ]);

    expect($version)->not->toBeNull()
        ->and($version->app_id)->toBe($app->id)
        ->and($version->version)->toBe('1.2.3');

    expect(AppVersion::where('app_id', $app->id)->count())->toBe(1);
});

it('saveVersion is idempotent for the same (app_id, version) — no duplicate row', function () {
    $app = App::factory()->create();

    $first = $this->syncer->saveVersion($app, ['version' => '1.2.3']);
    $second = $this->syncer->saveVersion($app, ['version' => '1.2.3']);

    expect($second->id)->toBe($first->id);
    expect(AppVersion::where('app_id', $app->id)->count())->toBe(1);
});

// ─── saveMetric ───────────────────────────────────────────────────

it('saveMetric creates a new metric row when none exists for (app, country, date)', function () {
    $app = App::factory()->create();
    $version = AppVersion::factory()->create(['app_id' => $app->id]);

    $this->syncer->saveMetric($app, $version, 'us', [
        'rating' => 4.5,
        'rating_count' => 100,
    ]);

    $metric = AppMetric::where('app_id', $app->id)
        ->where('country_code', 'us')
        ->first();

    expect($metric)->not->toBeNull()
        ->and((float) $metric->rating)->toBe(4.5)
        ->and($metric->rating_count)->toBe(100)
        ->and($metric->version_id)->toBe($version->id)
        ->and($metric->is_available)->toBeTrue();
});

it('saveMetric updates an existing metric row for the same (app, country, date) instead of inserting a duplicate', function () {
    $app = App::factory()->create();
    $version = AppVersion::factory()->create(['app_id' => $app->id]);

    $this->syncer->saveMetric($app, $version, 'us', ['rating' => 4.0, 'rating_count' => 50]);
    $this->syncer->saveMetric($app, $version, 'us', ['rating' => 4.7, 'rating_count' => 200]);

    expect(AppMetric::where('app_id', $app->id)->where('country_code', 'us')->count())->toBe(1);

    $metric = AppMetric::where('app_id', $app->id)->where('country_code', 'us')->first();
    expect((float) $metric->rating)->toBe(4.7)
        ->and($metric->rating_count)->toBe(200);
});

it('saveMetric writes a row with is_available=false when the storefront returned an empty response', function () {
    $app = App::factory()->create();
    $version = AppVersion::factory()->create(['app_id' => $app->id]);

    $this->syncer->saveMetric($app, $version, 'tr', [], isAvailable: false);

    $metric = AppMetric::where('app_id', $app->id)
        ->where('country_code', 'tr')
        ->first();

    expect($metric)->not->toBeNull()
        ->and($metric->is_available)->toBeFalse()
        ->and($metric->rating_count)->toBe(0);
});

it('saveMetric accepts a null version (e.g. when identity gave no version string)', function () {
    $app = App::factory()->create();

    $this->syncer->saveMetric($app, null, 'us', ['rating' => 3.2, 'rating_count' => 10]);

    $metric = AppMetric::where('app_id', $app->id)->where('country_code', 'us')->first();
    expect($metric)->not->toBeNull()
        ->and($metric->version_id)->toBeNull();
});

// ─── updateVersionDetails ─────────────────────────────────────────

it('updateVersionDetails copies file_size_bytes from the identity payload onto the version', function () {
    $app = App::factory()->create(['origin_country_code' => 'us']);
    $version = AppVersion::factory()->create([
        'app_id' => $app->id,
        'file_size_bytes' => null,
    ]);

    $this->syncer->updateVersionDetails($app, $version, [
        'file_size_bytes' => 73822208,
    ]);

    expect($version->fresh()->file_size_bytes)->toBe(73822208);
});

it('updateVersionDetails keeps the existing file_size_bytes when identity does not provide one', function () {
    $app = App::factory()->create(['origin_country_code' => 'us']);
    $version = AppVersion::factory()->create([
        'app_id' => $app->id,
        'file_size_bytes' => 12345,
    ]);

    $this->syncer->updateVersionDetails($app, $version, []);

    expect($version->fresh()->file_size_bytes)->toBe(12345);
});

// ─── detectChanges (field diff) ────────────────────────────────────

it('detectChanges writes one StoreListingChange row for a changed title', function () {
    $app = App::factory()->create();
    $oldVersion = AppVersion::factory()->create(['app_id' => $app->id]);
    $newVersion = AppVersion::factory()->create(['app_id' => $app->id]);

    $existing = StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $oldVersion->id,
        'locale' => 'en-US',
        'title' => 'Old Title',
        'subtitle' => 'Same Subtitle',
        'description' => 'Same description',
        'whats_new' => null,
    ]);

    $this->syncer->detectChanges($app, $existing, [
        'title' => 'New Title',
        'subtitle' => 'Same Subtitle',
        'description' => 'Same description',
        'whats_new' => null,
    ], $newVersion);

    $changes = StoreListingChange::where('app_id', $app->id)
        ->where('version_id', $newVersion->id)
        ->where('locale', 'en-US')
        ->get();

    expect($changes)->toHaveCount(1);
    expect($changes->first()->field_changed)->toBe('title');
    expect($changes->first()->old_value)->toBe('Old Title');
    expect($changes->first()->new_value)->toBe('New Title');
});

it('detectChanges does not duplicate rows when called twice for the same diff', function () {
    $app = App::factory()->create();
    $oldVersion = AppVersion::factory()->create(['app_id' => $app->id]);
    $newVersion = AppVersion::factory()->create(['app_id' => $app->id]);

    $existing = StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $oldVersion->id,
        'locale' => 'en-US',
        'title' => 'Old Title',
    ]);

    $newData = [
        'title' => 'New Title',
        'subtitle' => $existing->subtitle,
        'description' => $existing->description,
        'whats_new' => $existing->whats_new,
    ];

    $this->syncer->detectChanges($app, $existing, $newData, $newVersion);
    $this->syncer->detectChanges($app, $existing, $newData, $newVersion);

    expect(StoreListingChange::where('app_id', $app->id)
        ->where('field_changed', 'title')
        ->count())->toBe(1);
});

it('saveListing does not emit a diff when the previous listing is from the same version (existing.version_id === version.id)', function () {
    // This guards the bug fix where partial second-pass scrapes for the same
    // version were producing phantom change rows.
    $app = App::factory()->create();
    $version = AppVersion::factory()->create(['app_id' => $app->id]);

    // First scrape — establishes the row for this version.
    $this->syncer->saveListing($app, [
        'locale' => 'en-US',
        'title' => 'Title A',
        'description' => 'desc A',
    ], $version);

    // Second scrape for the SAME version with a different title — must NOT
    // log a change because version_id matches the existing row.
    $this->syncer->saveListing($app, [
        'locale' => 'en-US',
        'title' => 'Title B',
        'description' => 'desc A',
    ], $version);

    expect(StoreListingChange::where('app_id', $app->id)
        ->where('field_changed', 'title')
        ->count())->toBe(0);
});

// ─── detectLocaleChanges ──────────────────────────────────────────

it('detectLocaleChanges writes a locale_added row for every locale present only in the current version', function () {
    $app = App::factory()->create();
    $previous = AppVersion::factory()->create(['app_id' => $app->id]);
    $current = AppVersion::factory()->create(['app_id' => $app->id]);

    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $previous->id,
        'locale' => 'en-US',
    ]);

    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $current->id,
        'locale' => 'en-US',
    ]);
    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $current->id,
        'locale' => 'tr',
        'title' => 'Türkçe Başlık',
    ]);

    $this->syncer->detectLocaleChanges($app, $current);

    $change = StoreListingChange::where('app_id', $app->id)
        ->where('version_id', $current->id)
        ->where('field_changed', 'locale_added')
        ->where('locale', 'tr')
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->old_value)->toBeNull()
        ->and($change->new_value)->toBe('Türkçe Başlık');
});

it('detectLocaleChanges writes a locale_removed row for every locale dropped between previous and current versions', function () {
    $app = App::factory()->create();
    $previous = AppVersion::factory()->create(['app_id' => $app->id]);
    $current = AppVersion::factory()->create(['app_id' => $app->id]);

    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $previous->id,
        'locale' => 'en-US',
    ]);
    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $previous->id,
        'locale' => 'fr',
        'title' => 'Titre Français',
    ]);

    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $current->id,
        'locale' => 'en-US',
    ]);

    $this->syncer->detectLocaleChanges($app, $current);

    $change = StoreListingChange::where('app_id', $app->id)
        ->where('version_id', $current->id)
        ->where('field_changed', 'locale_removed')
        ->where('locale', 'fr')
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->old_value)->toBe('Titre Français')
        ->and($change->new_value)->toBeNull();
});

it('detectLocaleChanges is a no-op when there is no previous version', function () {
    $app = App::factory()->create();
    $current = AppVersion::factory()->create(['app_id' => $app->id]);

    StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $current->id,
        'locale' => 'en-US',
    ]);

    $this->syncer->detectLocaleChanges($app, $current);

    expect(StoreListingChange::where('app_id', $app->id)->count())->toBe(0);
});

it('detectLocaleChanges is a no-op when given a null current version', function () {
    $app = App::factory()->create();

    $this->syncer->detectLocaleChanges($app, null);

    expect(StoreListingChange::where('app_id', $app->id)->count())->toBe(0);
});
