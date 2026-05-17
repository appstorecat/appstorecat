<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\AppVersion;
use App\Models\StoreListing;
use App\Services\KeywordAnalyzer;

beforeEach(function () {
    $this->analyzer = new KeywordAnalyzer;
});

/**
 * Pick the density row matching a keyword + ngram size from the analyzer's output.
 * Namespaced to avoid collisions across Pest test files that share a single
 * process at runtime.
 *
 * @param  array<int, array{keyword: string, count: int, density: float, ngram_size: int}>  $rows
 */
function findKeyword(array $rows, string $keyword, int $ngramSize = 1): ?array
{
    foreach ($rows as $row) {
        if ($row['keyword'] === $keyword && $row['ngram_size'] === $ngramSize) {
            return $row;
        }
    }

    return null;
}

it('returns 1-gram, 2-gram and 3-gram rows for a simple input', function () {
    $rows = $this->analyzer->analyzeText('photo editor photo editor app');

    $sizes = array_unique(array_map(fn ($r) => $r['ngram_size'], $rows));
    sort($sizes);

    expect($sizes)->toBe([1, 2, 3]);
});

it('computes 1-gram count and density correctly', function () {
    // tokens (after stopword + length filter): photo, editor, photo, editor, app => 5 tokens
    $rows = $this->analyzer->analyzeText('photo editor photo editor app');

    $photo = findKeyword($rows, 'photo', 1);
    $editor = findKeyword($rows, 'editor', 1);
    $app = findKeyword($rows, 'app', 1);

    expect($photo)->not->toBeNull()
        ->and($photo['count'])->toBe(2)
        ->and($photo['density'])->toBe(40.0);

    expect($editor['count'])->toBe(2)
        ->and($editor['density'])->toBe(40.0);

    expect($app['count'])->toBe(1)
        ->and($app['density'])->toBe(20.0);
});

it('computes 2-gram counts correctly', function () {
    $rows = $this->analyzer->analyzeText('photo editor photo editor app');

    $photoEditor = findKeyword($rows, 'photo editor', 2);
    $editorPhoto = findKeyword($rows, 'editor photo', 2);

    expect($photoEditor)->not->toBeNull()
        ->and($photoEditor['count'])->toBe(2);

    expect($editorPhoto)->not->toBeNull()
        ->and($editorPhoto['count'])->toBe(1);
});

it('computes 3-gram counts correctly', function () {
    $rows = $this->analyzer->analyzeText('photo editor photo editor app');

    $a = findKeyword($rows, 'photo editor photo', 3);
    $b = findKeyword($rows, 'editor photo editor', 3);
    $c = findKeyword($rows, 'photo editor app', 3);

    expect($a['count'])->toBe(1)
        ->and($b['count'])->toBe(1)
        ->and($c['count'])->toBe(1);
});

it('filters out English stopwords (the, and, is) from the token stream', function () {
    $rows = $this->analyzer->analyzeText('the photo and the editor is great');

    expect(findKeyword($rows, 'the', 1))->toBeNull();
    expect(findKeyword($rows, 'and', 1))->toBeNull();
    expect(findKeyword($rows, 'is', 1))->toBeNull();

    expect(findKeyword($rows, 'photo', 1))->not->toBeNull();
    expect(findKeyword($rows, 'editor', 1))->not->toBeNull();
    expect(findKeyword($rows, 'great', 1))->not->toBeNull();
});

it('drops 1-character tokens (MIN_KEYWORD_LENGTH = 2)', function () {
    $rows = $this->analyzer->analyzeText('a photo z editor');

    expect(findKeyword($rows, 'a', 1))->toBeNull();
    expect(findKeyword($rows, 'z', 1))->toBeNull();
});

it('lowercases tokens before counting', function () {
    $rows = $this->analyzer->analyzeText('Photo PHOTO photo');

    $photo = findKeyword($rows, 'photo', 1);
    expect($photo)->not->toBeNull()
        ->and($photo['count'])->toBe(3);
});

it('strips HTML tags and URLs before tokenising', function () {
    $rows = $this->analyzer->analyzeText('<p>visit https://example.com for the photo editor</p>');

    expect(findKeyword($rows, 'https', 1))->toBeNull();
    expect(findKeyword($rows, 'example', 1))->toBeNull();
    expect(findKeyword($rows, 'com', 1))->toBeNull();
    expect(findKeyword($rows, 'photo', 1))->not->toBeNull();
    expect(findKeyword($rows, 'editor', 1))->not->toBeNull();
});

it('returns an empty array when the listing has no usable text', function () {
    $app = App::factory()->create();
    $version = AppVersion::factory()->create(['app_id' => $app->id]);

    $listing = StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $version->id,
        'title' => '',
        'subtitle' => null,
        'description' => '',
        'whats_new' => null,
    ]);

    expect($this->analyzer->analyzeListing($listing))->toBe([]);
});

it('returns an empty array when analyzeText receives an empty string', function () {
    expect($this->analyzer->analyzeText(''))->toBe([]);
    expect($this->analyzer->analyzeText('   '))->toBe([]);
});

it('returns an empty array when only stopwords remain after filtering', function () {
    expect($this->analyzer->analyzeText('the and is of a'))->toBe([]);
});

it('handles a null subtitle without crashing and still counts title + description tokens', function () {
    $app = App::factory()->create();
    $version = AppVersion::factory()->create(['app_id' => $app->id]);

    $listing = StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $version->id,
        'title' => 'PhotoApp',
        'subtitle' => null,
        'description' => 'edit photos quickly',
        'whats_new' => null,
    ]);

    $rows = $this->analyzer->analyzeListing($listing);

    expect($rows)->not->toBe([]);
    expect(findKeyword($rows, 'photoapp', 1))->not->toBeNull();
    expect(findKeyword($rows, 'photos', 1))->not->toBeNull();
    expect(findKeyword($rows, 'edit', 1))->not->toBeNull();
});

it('merges title + subtitle + description + whats_new into the same token stream', function () {
    $app = App::factory()->create();
    $version = AppVersion::factory()->create(['app_id' => $app->id]);

    $listing = StoreListing::factory()->create([
        'app_id' => $app->id,
        'version_id' => $version->id,
        'title' => 'titleword',
        'subtitle' => 'subtitleword',
        'description' => 'descriptionword',
        'whats_new' => 'whatsnewword',
    ]);

    $rows = $this->analyzer->analyzeListing($listing);

    expect(findKeyword($rows, 'titleword', 1))->not->toBeNull();
    expect(findKeyword($rows, 'subtitleword', 1))->not->toBeNull();
    expect(findKeyword($rows, 'descriptionword', 1))->not->toBeNull();
    expect(findKeyword($rows, 'whatsnewword', 1))->not->toBeNull();
});
