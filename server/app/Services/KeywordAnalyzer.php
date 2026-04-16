<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\App;
use App\Models\AppKeywordDensity;
use App\Models\StoreListing;

class KeywordAnalyzer
{
    private const MAX_NGRAM = 3;

    private const MIN_KEYWORD_LENGTH = 2;

    /** @var array<string, array<string, true>> */
    private array $stopWordsCache = [];

    public function analyze(App $app, int $versionId, string $language, string $description): void
    {
        // Delete existing data for this version+language, recalculate fresh
        AppKeywordDensity::where('app_id', $app->id)
            ->where('version_id', $versionId)
            ->where('language', $language)
            ->delete();

        $words = $this->tokenize($description, $language);

        if (count($words) === 0) {
            return;
        }

        $totalWords = count($words);
        $records = [];

        for ($n = 1; $n <= self::MAX_NGRAM; $n++) {
            $ngrams = $this->extractNgrams($words, $n);

            foreach ($ngrams as $keyword => $count) {
                $density = round(($count / $totalWords) * 100, 2);
                $records[] = [
                    'app_id' => $app->id,
                    'version_id' => $versionId,
                    'language' => $language,
                    'ngram_size' => $n,
                    'keyword' => $keyword,
                    'count' => $count,
                    'density' => $density,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($records)) {
            AppKeywordDensity::insert($records);
        }
    }

    public function analyzeFromListing(App $app, StoreListing $listing): void
    {
        if (! $listing->version_id) {
            return;
        }

        $text = implode(' ', array_filter([
            $listing->title,
            $listing->subtitle,
            $listing->description,
            $listing->whats_new,
        ]));

        if (trim($text) === '') {
            return;
        }

        $this->analyze($app, $listing->version_id, $listing->language, $text);
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text, string $language = 'en-US'): array
    {
        $text = strip_tags($text);

        // Remove URLs
        $text = preg_replace('/https?:\/\/\S+/i', ' ', $text);

        $text = mb_strtolower($text);

        // Keep only letters, numbers and spaces
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        $words = explode(' ', trim($text));

        $stopWords = $this->loadStopWords($language);

        return array_values(array_filter($words, fn (string $word) => strlen($word) >= self::MIN_KEYWORD_LENGTH && ! isset($stopWords[$word])));
    }

    private function loadStopWords(string $language): array
    {
        // Extract base language from locale code (e.g. 'en-GB' → 'en', 'tr-TR' → 'tr', 'zh-Hans' → 'zh')
        $lang = strtok(strtolower($language), '-');

        $cacheKey = $lang;

        if (isset($this->stopWordsCache[$cacheKey])) {
            return $this->stopWordsCache[$cacheKey];
        }

        // Always load English stop words
        $words = $this->readStopWordsFile('en');

        // Add locale-specific stop words
        if ($lang !== 'en') {
            $words = array_merge($words, $this->readStopWordsFile($lang));
        }

        // Use keys for O(1) lookup
        $this->stopWordsCache[$cacheKey] = array_fill_keys($words, true);

        return $this->stopWordsCache[$cacheKey];
    }

    /**
     * @return string[]
     */
    private function readStopWordsFile(string $lang): array
    {
        $path = resource_path("data/stopwords/{$lang}.json");

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? array_map('mb_strtolower', $data) : [];
    }

    /**
     * @param  string[]  $words
     * @return array<string, int>
     */
    private function extractNgrams(array $words, int $n): array
    {
        $ngrams = [];
        $count = count($words);

        for ($i = 0; $i <= $count - $n; $i++) {
            $ngram = implode(' ', array_slice($words, $i, $n));
            $ngrams[$ngram] = ($ngrams[$ngram] ?? 0) + 1;
        }

        arsort($ngrams);

        return $ngrams;
    }
}
