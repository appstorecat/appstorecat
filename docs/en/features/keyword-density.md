# Keyword Density

Analyze keyword usage in app store listings with n-gram extraction and cross-app comparison.

![Keyword Density](../../screenshots/keyword-density.jpeg)

## Overview

AppStoreCat extracts keywords from store listings and calculates their frequency and density. This helps with App Store Optimization (ASO) by showing which keywords an app targets and how they compare across competitors.

## How It Works

Keyword density is computed on-the-fly from the current `StoreListing` whenever the API is called — nothing is persisted between requests. This means analysis always reflects the latest listing text without needing a reindex step.

1. The keyword endpoint loads the matching `StoreListing` for the requested platform + external ID + language
2. Text from title + subtitle + description + what's new is combined
3. Content is tokenized with language-aware stop word filtering
4. N-grams (1-word, 2-word, 3-word combinations) are extracted
5. Frequency and density percentage are calculated and returned in the response

## Stop Word Filtering

The analyzer includes stop word dictionaries for **50 languages**. Stop words (common words like "the", "and", "is") are filtered out to surface meaningful keywords.

Stop word files are stored in `server/resources/data/stopwords/{lang}.json`.

## N-gram Support

| N-gram Size | Example |
|-------------|---------|
| 1 (unigram) | `photo`, `editor`, `filter` |
| 2 (bigram) | `photo editor`, `social media` |
| 3 (trigram) | `photo editing app`, `free music player` |

## API

### Keyword Density

```
GET /api/v1/apps/{platform}/{externalId}/keywords?language=en-US&ngram=2
```

Returns keywords with their count and density percentage for the specified listing. The analyzer runs on every request against the current stored listing, so results automatically pick up any new sync data.

### Keyword Comparison

```
GET /api/v1/apps/{platform}/{externalId}/keywords/compare?app_ids=1,2,3&language=en
```

Compares keyword usage across multiple apps — useful for competitive keyword analysis.

## Frontend

The **Keywords** tab on the app detail page shows:
- Keyword list sorted by density
- N-gram size filter (1, 2, 3)
- Language selector
- Comparison view with competitor apps

## Technical Details

- **Service:** `KeywordAnalyzer` (`analyzeListing()` / `analyzeText()` return arrays — no DB writes)
- **Source:** Current `StoreListing` row for the requested `(app_id, language)` tuple
- **Controllers:** `KeywordController@index` and `KeywordController@compare` compute fresh on every request
- **Minimum word length:** 2 characters
