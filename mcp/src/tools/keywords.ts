import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet, buildPath } from '../client.js';
import { ExternalId, NgramSmall, Page, Platform } from './_schemas.js';

export function registerKeywordTools(server: McpServer): void {
  server.registerTool(
    'get_app_keywords',
    {
      description:
        'Keyword density breakdown for an app\'s store listing. ' +
        '`version_id` is optional — leave empty for the latest version, or pass an id from ' +
        'get_app_listing / list_app_changes / get_app.versions to pin a specific version. ' +
        'Returns paginated { locale, ngram_size, keyword, count, density } rows.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
        locale: z.string().optional(),
        ngram: NgramSmall.optional(),
        version_id: z.number().int().optional(),
        search: z.string().optional(),
        sort: z.enum(['keyword', 'count', 'density']).optional(),
        order: z.enum(['asc', 'desc']).optional(),
        per_page: z.number().int().min(1).max(500).optional(),
        page: Page.optional(),
      },
    },
    async ({
      platform,
      external_id,
      locale,
      ngram,
      version_id,
      search,
      sort,
      order,
      per_page,
      page,
    }) => {
      const path = buildPath('/apps/{platform}/{externalId}/keywords', {
        platform,
        externalId: external_id,
      });
      return apiGet(path, {
        locale,
        ngram,
        version_id,
        search,
        sort,
        order,
        per_page,
        page,
      });
    },
  );

  server.registerTool(
    'compare_app_keywords',
    {
      description:
        'Compare keyword density between an app and up to 5 of its tracked competitors. ' +
        '`app_ids` are INTERNAL integer ids of competitor apps (from list_tracked_apps.id or list_app_competitors entries). ' +
        '`version_ids` is an optional map of { app_id: version_id } using the same internal app ids. ' +
        'Returns { apps, keywords } where `keywords` is keyed by app id.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
        app_ids: z.array(z.number().int()).min(1).max(5),
        version_ids: z.record(z.string(), z.number().int()).optional(),
        locale: z.string().optional(),
        ngram: NgramSmall.optional(),
      },
    },
    async ({
      platform,
      external_id,
      app_ids,
      version_ids,
      locale,
      ngram,
    }) => {
      const path = buildPath(
        '/apps/{platform}/{externalId}/keywords/compare',
        { platform, externalId: external_id },
      );
      return apiGet(path, {
        app_ids,
        version_ids,
        locale,
        ngram,
      });
    },
  );
}
