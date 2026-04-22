import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet, buildPath } from '../client.js';
import { ExternalId, Platform } from './_schemas.js';

export function registerPublisherTools(server: McpServer): void {
  server.registerTool(
    'search_publishers',
    {
      description:
        'Search publishers on the live App Store / Google Play. ' +
        'Returns { external_id, name, platform, app_count, sample_apps }. ' +
        'Use { platform, external_id } with get_publisher or get_publisher_store_apps. ' +
        '`sample_apps[].external_id` → get_app.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        term: z.string().min(1),
        platform: Platform,
        country_code: z.string().optional(),
      },
    },
    async ({ term, platform, country_code }) => {
      return apiGet('/publishers/search', { term, platform, country_code });
    },
  );

  server.registerTool(
    'list_user_publishers',
    {
      description:
        'List publishers derived from the user\'s tracked apps. ' +
        'Use { platform, external_id } of each publisher with get_publisher.',
      annotations: { readOnlyHint: true },
      inputSchema: {},
    },
    async () => {
      return apiGet('/publishers');
    },
  );

  server.registerTool(
    'get_publisher',
    {
      description:
        'Get publisher details including tracked apps belonging to this publisher. ' +
        'Returns { publisher, apps[] }. ' +
        'Use `apps[].{platform, external_id}` with get_app and other app tools. ' +
        'Optional `name` disambiguates when the store returns multiple matches for the same external_id.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
        name: z.string().optional(),
      },
    },
    async ({ platform, external_id, name }) => {
      const path = buildPath('/publishers/{platform}/{externalId}', {
        platform,
        externalId: external_id,
      });
      return apiGet(path, { name });
    },
  );

  server.registerTool(
    'get_publisher_store_apps',
    {
      description:
        'Fetch ALL apps by a publisher directly from the live store (not limited to tracked apps). ' +
        'Returns { external_id, name, is_tracked, ... } per app. ' +
        'Untracked entries (is_tracked=false) are discovery candidates — track them via the web UI if desired.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath(
        '/publishers/{platform}/{externalId}/store-apps',
        { platform, externalId: external_id },
      );
      return apiGet(path);
    },
  );
}
