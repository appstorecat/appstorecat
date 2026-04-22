import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet, buildPath } from '../client.js';
import { ExternalId, Platform } from './_schemas.js';

export function registerCompetitorTools(server: McpServer): void {
  server.registerTool(
    'list_app_competitors',
    {
      description:
        'List competitor apps for a tracked app. Each competitor exposes { platform, external_id }. ' +
        'Recurse with get_app, get_app_listing, or list_app_competitors (second-degree competitors).',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath('/apps/{platform}/{externalId}/competitors', {
        platform,
        externalId: external_id,
      });
      return apiGet(path);
    },
  );

  server.registerTool(
    'list_all_competitors',
    {
      description:
        'List competitors grouped by parent app across all of the user\'s tracked apps. ' +
        'Returns { parent, competitors[] } groups — both sides carry { platform, external_id } usable with get_app.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform.optional(),
        search: z.string().optional(),
      },
    },
    async ({ platform, search }) => {
      return apiGet('/competitors', { platform, search });
    },
  );
}
