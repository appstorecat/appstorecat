import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';
import { Page, Platform } from './_schemas.js';

export function registerExplorerTools(server: McpServer): void {
  server.registerTool(
    'browse_screenshots',
    {
      description:
        'Browse app screenshots across all apps, filtered by platform/category/search. ' +
        'Returns { app_id, external_id, platform, name, icon_url, publisher_name, category_name, screenshots[] }. ' +
        '`category_id` comes from list_categories. ' +
        'Use { platform, external_id } with get_app for deeper metadata. ' +
        'Default per_page=12; raise carefully — responses can be large.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform.optional(),
        category_id: z.number().int().optional(),
        search: z.string().optional(),
        per_page: z.number().int().optional(),
        page: Page.optional(),
      },
    },
    async ({ platform, category_id, search, per_page, page }) => {
      return apiGet('/explorer/screenshots', {
        platform,
        category_id,
        search,
        per_page,
        page,
      });
    },
  );

  server.registerTool(
    'browse_icons',
    {
      description:
        'Browse app icons across all apps, filtered by platform/category/search. ' +
        'Returns { app_id, external_id, platform, name, icon_url, publisher_name, category_name }. ' +
        '`category_id` comes from list_categories. Default per_page=48.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform.optional(),
        category_id: z.number().int().optional(),
        search: z.string().optional(),
        per_page: z.number().int().optional(),
        page: Page.optional(),
      },
    },
    async ({ platform, category_id, search, per_page, page }) => {
      return apiGet('/explorer/icons', {
        platform,
        category_id,
        search,
        per_page,
        page,
      });
    },
  );
}
