import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';
import { Platform } from './_schemas.js';

export function registerReferenceTools(server: McpServer): void {
  server.registerTool(
    'list_categories',
    {
      description:
        'List store categories (games, productivity, etc.) for App Store and Google Play. ' +
        'Returns { id, name, slug, platform } per category. ' +
        'Use the returned `id` as `category_id` in: get_charts, browse_screenshots, browse_icons.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform.optional(),
        type: z.enum(['app', 'game', 'magazine']).optional(),
      },
    },
    async ({ platform, type }) => {
      return apiGet('/store-categories', { platform, type });
    },
  );

  server.registerTool(
    'list_countries',
    {
      description:
        'List active countries supported by the platform. Returns { code, name, emoji }. ' +
        'Use `code` as `country_code` in: get_app_listing, search_store_apps, get_charts, search_publishers.',
      annotations: { readOnlyHint: true },
      inputSchema: {},
    },
    async () => {
      return apiGet('/countries');
    },
  );
}
