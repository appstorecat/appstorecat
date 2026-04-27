// @ts-check
import { defineConfig } from 'astro/config'
import starlight from '@astrojs/starlight'

const SITE = process.env.DOCS_SITE_URL || 'https://appstorecat.github.io'
const BASE = process.env.DOCS_SITE_BASE || '/appstorecat'
const REPO = 'https://github.com/appstorecat/appstorecat'

export default defineConfig({
  site: SITE,
  base: BASE,
  trailingSlash: 'never',
  // The installation page IS the docs homepage now. Anyone arriving at the
  // old URL gets pushed to the root.
  redirects: {
    '/getting-started/installation': '/',
  },
  integrations: [
    starlight({
      title: 'AppStoreCat',
      description:
        'Open-source, self-hosted App Store & Google Play intelligence — with a 28-tool MCP server for Claude Code.',
      logo: {
        light: './src/assets/logo.svg',
        dark: './src/assets/logo.svg',
        replacesTitle: false,
      },
      favicon: '/favicon.ico',
      social: [
        { icon: 'github', label: 'GitHub', href: REPO },
        { icon: 'npm', label: 'npm (@appstorecat/mcp)', href: 'https://www.npmjs.com/package/@appstorecat/mcp' },
      ],
      editLink: {
        baseUrl: `${REPO}/edit/master/docs-site/`,
      },
      lastUpdated: true,
      pagination: true,
      customCss: ['./src/styles/theme.css'],
      head: [
        {
          tag: 'meta',
          attrs: { name: 'theme-color', content: '#10b981' },
        },
        {
          tag: 'meta',
          attrs: {
            property: 'og:image',
            content: `${SITE}${BASE}/og.png`,
          },
        },
      ],
      sidebar: [
        {
          label: 'Getting Started',
          items: [
            // Installation is the docs homepage (slug is '').
            { label: 'Installation', slug: '' },
            { label: 'Install Script', slug: 'getting-started/install-script' },
            { label: 'Configuration', slug: 'getting-started/configuration' },
            { label: 'Quick Start', slug: 'getting-started/quick-start' },
          ],
        },
        {
          label: 'Architecture',
          items: [
            { label: 'Overview', slug: 'architecture/overview' },
            { label: 'Data Model', slug: 'architecture/data-model' },
            { label: 'Data Collection', slug: 'architecture/data-collection' },
            { label: 'Queue System', slug: 'architecture/queue-system' },
            { label: 'Connectors', slug: 'architecture/connectors' },
            { label: 'Sync Pipeline', slug: 'architecture/sync-pipeline' },
          ],
        },
        {
          label: 'Services',
          items: [
            { label: 'Server', slug: 'services/server' },
            { label: 'Web', slug: 'services/web' },
            { label: 'App Store Scraper', slug: 'services/scraper-ios' },
            { label: 'Google Play Scraper', slug: 'services/scraper-android' },
            {
              label: 'MCP Server',
              slug: 'services/mcp',
              badge: { text: 'AI', variant: 'success' },
            },
          ],
        },
        {
          label: 'Features',
          items: [
            { label: 'Trending Charts', slug: 'features/trending-charts' },
            { label: 'App Rankings', slug: 'features/app-rankings' },
            { label: 'App Discovery', slug: 'features/app-discovery' },
            { label: 'Store Listings', slug: 'features/store-listings' },
            { label: 'Ratings', slug: 'features/ratings' },
            { label: 'Keyword Density', slug: 'features/keyword-density' },
            { label: 'Competitor Tracking', slug: 'features/competitor-tracking' },
            { label: 'Change Detection', slug: 'features/change-detection' },
            { label: 'Publisher Discovery', slug: 'features/publisher-discovery' },
            { label: 'Media & Explorer', slug: 'features/media-proxy' },
          ],
        },
        {
          label: 'API',
          items: [
            { label: 'Endpoints', slug: 'api/endpoints' },
            { label: 'Authentication', slug: 'api/authentication' },
            { label: 'Scraper APIs', slug: 'api/scraper-apis' },
          ],
        },
        {
          label: 'Deployment',
          items: [
            { label: 'Docker', slug: 'deployment/docker' },
            { label: 'Production', slug: 'deployment/production' },
            { label: 'Troubleshooting', slug: 'deployment/troubleshooting' },
          ],
        },
        {
          label: 'Reference',
          items: [
            { label: 'Environment Variables', slug: 'reference/environment-variables' },
            { label: 'Makefile Commands', slug: 'reference/makefile-commands' },
            { label: 'App Store Countries', slug: 'reference/app-store-countries' },
          ],
        },
      ],
    }),
  ],
})
