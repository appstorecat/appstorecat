import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet, buildPath } from '../client.js';
import { DateStr, ExternalId, Platform } from './_schemas.js';

export function registerChartTools(server: McpServer): void {
  server.registerTool(
    'get_charts',
    {
      description:
        'Get store chart rankings (top_free / top_paid / top_grossing). ' +
        'Prerequisite: call list_categories for `category_id` and list_countries for `country_code`. ' +
        'Each entry exposes { rank, rank_change, app_id, app_external_id, platform, publisher, category_name, price }. ' +
        'Use { platform, app_external_id } with: get_app, get_app_listing, get_rating_summary.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        collection: z.enum(['top_free', 'top_paid', 'top_grossing']),
        country_code: z.string().optional(),
        category_id: z.number().int().optional(),
      },
    },
    async ({ platform, collection, country_code, category_id }) => {
      return apiGet('/charts', {
        platform,
        collection,
        country_code,
        category_id,
      });
    },
  );

  server.registerTool(
    'get_app_rankings',
    {
      description:
        'List chart rankings for an app on a given date (which countries / collections / categories it charts in). ' +
        'Returns { country_code, collection, category, rank, previous_rank, rank_change, status, snapshot_date }. ' +
        '`collection=all` returns every collection. Join `category.id` with list_categories for metadata.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
        date: DateStr.optional(),
        collection: z
          .enum(['top_free', 'top_paid', 'top_grossing', 'all'])
          .optional(),
      },
    },
    async ({ platform, external_id, date, collection }) => {
      const path = buildPath('/apps/{platform}/{externalId}/rankings', {
        platform,
        externalId: external_id,
      });
      return apiGet(path, { date, collection });
    },
  );
}
