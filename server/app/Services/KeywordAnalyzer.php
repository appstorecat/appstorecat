<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StoreListing;

class KeywordAnalyzer
{
    private const MAX_NGRAM = 3;

    private const MIN_KEYWORD_LENGTH = 2;

    /** @var array<string, array<string, true>> */
    private array $stopWordsCache = [];

    /**
     * Analyze a listing and return keyword density rows grouped by ngram size.
     *
     * @return array<int, array{keyword: string, count: int, density: float, ngram_size: int}>
     */
    public function analyzeListing(StoreListing $listing): array
    {
        $text = implode(' ', array_filter([
            $listing->title,
            $listing->subtitle,
            $listing->description,
            $listing->whats_new,
        ]));

        if (trim($text) === '') {
            return [];
        }

        return $this->analyzeText($text, $listing->language);
    }

    /**
     * @return array<int, array{keyword: string, count: int, density: float, ngram_size: int}>
     */
    public function analyzeText(string $text, string $language = 'en-US'): array
    {
        $words = $this->tokenize($text, $language);
        if (count($words) === 0) {
            return [];
        }

        $totalWords = count($words);
        $results = [];

        for ($n = 1; $n <= self::MAX_NGRAM; $n++) {
            $ngrams = $this->extractNgrams($words, $n);
            foreach ($ngrams as $keyword => $count) {
                $results[] = [
                    'keyword' => $keyword,
                    'count' => $count,
                    'density' => round(($count / $totalWords) * 100, 2),
                    'ngram_size' => $n,
                ];
            }
        }

        return $results;
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text, string $language = 'en-US'): array
    {
        $text = strip_tags($text);
        $text = preg_replace('/https?:\/\/\S+/i', ' ', $text);
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        $words = explode(' ', trim($text));
        $stopWords = $this->loadStopWords($language);

        return array_values(array_filter($words, fn (string $word) => strlen($word) >= self::MIN_KEYWORD_LENGTH && ! isset($stopWords[$word])));
    }

    /**
     * @return array<string, true>
     */
    private function loadStopWords(string $language): array
    {
        $lang = strtok(strtolower($language), '-');
        if (isset($this->stopWordsCache[$lang])) {
            return $this->stopWordsCache[$lang];
        }

        $words = $this->readStopWordsFile('en');
        if ($lang !== 'en') {
            $words = array_merge($words, $this->readStopWordsFile($lang));
        }

        $this->stopWordsCache[$lang] = array_fill_keys($words, true);

        return $this->stopWordsCache[$lang];
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
