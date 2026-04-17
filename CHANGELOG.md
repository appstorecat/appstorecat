# Changelog

All notable changes to AppStoreCat will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Open-source documentation restructuring
- Job performance logging for queue monitoring
- Rankings tab on app detail page with country/collection/category pivot and date navigation

### Fixed
- Chart fetch exceptions now always propagate for retry and failed_jobs tracking
- Fallback to web-scraped iPhone screenshots when iTunes API returns none
- App icon aspect ratio in explorer icons page

## [0.0.3] - 2026-04-01

### Added
- Multi-language store listing support across full stack
- Multi-country support with per-platform country activation
- Price and currency tracking across all layers
- Daily chart sync with priority and platform-separated queues
- Stop words filter for keyword analysis (50 languages)
- Searchable country/language selectors
- Removed from Store badge for unavailable apps
- Explorer pages for screenshots and app icons
- Review sync pipeline with multi-page support
- Configurable app discovery per source
- App sync pipeline with dual queue system (tracked/discovery)
- Trending charts with automatic data collection
- Media proxy for app icons and listing assets
- Publisher discovery and search

### Changed
- Refactored to platform-separated queue architecture (iOS/Android independent)
- Refactored throttling to job-level with Redis
- Split all config into per-platform iOS/Android keys
- Language-based unique listings (removed country_code dependency)
- Renamed reviews table to app_reviews

### Removed
- DNA build pipeline (replaced by trending-based data collection)
- Unused Dashboard page and AddAppModal component
- Unused database columns and schema cleanup

## [0.0.2] - 2026-03-01

### Added
- Production Docker deployment with supervisor
- Queue worker and scheduler configuration
- Orval API client generation

### Changed
- Optimized Dockerfile layer caching and image size
- Renamed Docker services with appstorecat- prefix
- Renamed Category to StoreCategory with seeded data

### Fixed
- Field-level validation errors on auth forms
- Absolute URLs for storage images in API responses
- npm vulnerability fixes

## 0.0.1 - 2026-02-15

### Added
- Initial Laravel project setup
- Database schema and core models
- Store connectors for iTunes and Google Play
- Auth API with Sanctum token authentication
- App tracking and store search API
- Store listing and version endpoints
- Reviews API with filters and rating summary
- Keyword density analysis and comparison API
- Competitor tracking API
- Dashboard summary API
- Publisher pages API with search and bulk import
- OpenAPI documentation with L5-Swagger
- React frontend with Vite and shadcn/ui
- Frontend auth, app detail, keyword, competitor, changes, publisher, settings pages
- Sidebar navigation with theme toggle

[Unreleased]: https://github.com/appstorecat/appstorecat/compare/v0.0.3...HEAD
[0.0.3]: https://github.com/appstorecat/appstorecat/compare/v0.0.2...v0.0.3
[0.0.2]: https://github.com/appstorecat/appstorecat/releases/tag/v0.0.2
