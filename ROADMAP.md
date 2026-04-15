# Roadmap

AppStoreCat development roadmap. Items are grouped by priority and may change based on community feedback.

## Completed (v0.0.3)

- [x] Trending chart sync with daily snapshots
- [x] App discovery from multiple sources (search, trending, publishers)
- [x] Multi-language store listing support
- [x] Multi-country support with per-platform activation
- [x] Price and currency tracking
- [x] Review sync with multi-page pagination
- [x] Keyword density analysis with 50-language stop words
- [x] Store listing change detection
- [x] Platform-separated queue architecture
- [x] Redis-based job throttling
- [x] Explorer pages for screenshots and icons
- [x] Production Docker deployment with Supervisor

## In Progress

- [ ] Improved sync scheduling (priority-based queue ordering)
- [ ] Multi-locale listing sync (fetch all supported locales per app)
- [ ] Historical chart ranking tracking with position trends

## Planned

### Data & Intelligence
- [ ] Similar apps discovery (store-suggested related apps)
- [ ] Category-level trending analysis
- [ ] Rating trend visualization over time
- [ ] ASO score calculation

### Frontend
- [ ] Dashboard with charts and activity feed
- [ ] Bulk operations (track/untrack multiple apps)
- [ ] Export data (CSV, JSON)
- [ ] Dark mode improvements

### Infrastructure
- [ ] Webhook notifications for changes
- [ ] Scheduled report generation
- [ ] API rate limit dashboard
- [ ] Multi-user workspace support

### Integrations
- [ ] Slack notifications for store listing changes
- [ ] Email alerts for rating drops
- [ ] CI/CD integration for automated store monitoring

## Contributing

Have a feature idea? [Open a feature request](https://github.com/appstorecat/appstorecat/issues/new?template=feature_request.yml) and let's discuss it.
