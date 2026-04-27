# AppStoreCat docs site

[Astro](https://astro.build) + [Starlight](https://starlight.astro.build) site for the AppStoreCat documentation. Deploys to GitHub Pages on every push to `master`.

> **Source of truth:** the Markdown files live in [`../docs/en/`](../docs/en/). This package mirrors them into `src/content/docs/` at build time, adding a frontmatter block where needed.

## Develop

```bash
make docs-install   # one-time: install Astro + Starlight
make docs-dev       # syncs docs and runs dev server on http://localhost:4321
```

Or directly:

```bash
cd docs-site
npm install
npm run sync-docs && npm run dev
```

## Build

```bash
make docs-build     # produces docs-site/dist/
```

The build is idempotent: `sync-docs` rewrites `src/content/docs/` from the canonical Markdown each time, so contributors only edit `docs/en/` and never `docs-site/src/content/docs/`.

## Deploy

The `.github/workflows/docs.yml` workflow handles deploys automatically:

- Triggered on push to `master` when files under `docs/**`, `docs-site/**`, or the workflow itself change.
- Builds the site with the right `BASE` for GitHub Pages (`/appstorecat`).
- Publishes to the `github-pages` environment.

The site URL is `https://appstorecat.github.io/appstorecat/`. To use a custom domain (e.g. `docs.appstore.cat`), drop a `CNAME` file in `public/` and set the same domain in repo settings → Pages.

## Adding a page

1. Create the Markdown file in `../docs/en/<category>/<slug>.md`.
2. Add an entry to the `sidebar` array in [`astro.config.mjs`](./astro.config.mjs).
3. Push — CI rebuilds and deploys.

## Project layout

```
docs-site/
├── astro.config.mjs       # Starlight config + sidebar
├── src/
│   ├── assets/logo.svg
│   ├── content/docs/      # auto-synced (gitignored)
│   └── styles/theme.css   # brand overrides
├── scripts/sync-docs.mjs  # mirrors ../docs/en/ → src/content/docs/
├── public/                # static assets (favicon, og image)
└── package.json
```
