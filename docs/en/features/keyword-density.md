# Keyword Density

Analyze keyword usage in app store listings with n-gram extraction and cross-app comparison.

![Keyword Density](../../../screenshots/keyword-density.jpeg)

## Overview

AppStoreCat extracts keywords from store listings and computes their frequency and density. This helps with App Store Optimization (ASO) by showing which keywords an app is targeting and how they compare across competitors.

## How It Works

Keyword density is computed on the fly from the existing `StoreListing` on every request — nothing is persisted between requests. The analysis therefore always reflects the latest listing text; no separate reindexing step is needed.

1. The keyword endpoint loads the matching `StoreListing` record for the requested platform + external ID + locale
2. Title + subtitle + description + what's new text is combined
3. The content is tokenized with language-aware stop-word filtering
4. N-grams (1-word, 2-word, 3-word combinations) are extracted
5. Frequency and density percentages are computed and returned in the response

## Stop-Word Filtering

The analyzer ships with stop-word dictionaries for **50 languages**. Stop words (common words like "the", "and", "is") are filtered out to surface meaningful keywords.

Stop-word files live at `server/resources/data/stopwords/{lang}.json`.

## N-gram Support

| N-gram size | Example |
|-------------|---------|
| 1 (unigram) | `photo`, `editor`, `filter` |
| 2 (bigram) | `photo editor`, `social media` |
| 3 (trigram) | `photo editing app`, `free music player` |

## API

### Keyword Density

```
GET /api/v1/apps/{platform}/{externalId}/keywords?locale=en-US&ngram=2
```

Returns the keywords for the given listing with count and density percentages. The analyzer re-runs against the current stored listing on every request; results automatically reflect the latest sync data.

### Keyword Comparison

```
GET /api/v1/apps/{platform}/{externalId}/keywords/compare?app_ids=1,2,3&locale=en-US
```

Compares keyword usage across several apps — useful for competitive keyword analysis.

## UI

The **Keywords** tab on the app detail page shows:
- A keyword list sorted by density
- N-gram size filter (1, 2, 3)
- Language picker
- Comparison view with competitor apps

## Technical Details

- **Service:** `KeywordAnalyzer` (`analyzeListing()` / `analyzeText()` return arrays — they do not write to the DB)
- **Source:** The current `StoreListing` record for the requested `(app_id, locale)`
- **Controllers:** `KeywordController@index` and `KeywordController@compare` recompute on every request
- **Minimum word length:** 2 characters
