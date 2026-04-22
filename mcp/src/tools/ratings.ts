import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet, buildPath } from '../client.js';
import { ExternalId, Platform } from './_schemas.js';

export function registerRatingTools(server: McpServer): void {
  server.registerTool(
    'get_rating_summary',
    {
      description:
        'Get the current rating + 30-day trend for an app. ' +
        'Returns { rating, rating_count, breakdown, trend }.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath(
        '/apps/{platform}/{externalId}/ratings/summary',
        { platform, externalId: external_id },
      );
      return apiGet(path);
    },
  );

  server.registerTool(
    'get_rating_history',
    {
      description:
        'Get daily rating history for the last N days (default 30, max 90). ' +
        'Returns a list of { date, rating, rating_count, breakdown, delta_breakdown, delta_total }.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
        days: z.number().int().min(1).max(90).optional(),
      },
    },
    async ({ platform, external_id, days }) => {
      const path = buildPath(
        '/apps/{platform}/{externalId}/ratings/history',
        { platform, externalId: external_id },
      );
      return apiGet(path, { days });
    },
  );

  server.registerTool(
    'get_rating_country_breakdown',
    {
      description:
        'Get the latest rating snapshot per country for an app (iOS only). ' +
        'Returns a list of { country_code, rating, rating_count }. ' +
        'Join `country_code` with list_countries for display names.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath(
        '/apps/{platform}/{externalId}/ratings/country-breakdown',
        { platform, externalId: external_id },
      );
      return apiGet(path);
    },
  );
}
