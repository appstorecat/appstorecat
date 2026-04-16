import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

const platformEnum = z.enum(['ios', 'android']).optional().describe('Platform filter: ios or android');
const platformRequired = z.enum(['ios', 'android']).describe('Platform: ios or android');
const externalId = z.string().describe('App external ID (e.g. 389801252 for iOS, com.instagram.android for Android)');

export function registerAppTools(server: McpServer) {
  server.registerTool('search_apps', {
    description:
      'Search for apps on App Store or Google Play by keyword. Returns matching apps from the stores.',
    inputSchema: {
      term: z.string().describe('Search keyword (e.g. "instagram", "todo app")'),
      platform: z.enum(['ios', 'android']).describe('Platform: ios or android'),
      country: z.string().optional().describe('Country code (e.g. us, tr, de). Defaults to us'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/apps/search', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('list_apps', {
    description:
      'List all tracked apps. These are apps the user has added to their tracking list.',
    inputSchema: {
      platform: platformEnum,
      page: z.number().optional().describe('Page number for pagination'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/apps', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('get_app', {
    description:
      'Get detailed information about a specific app including metadata, ratings, version history, and tracking status.',
    inputSchema: {
      platform: platformRequired,
      external_id: externalId,
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet(`/apps/${args.platform}/${args.external_id}`);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('get_app_listing', {
    description:
      'Get the current store listing for an app: description, screenshots, what\'s new text, version info, and all metadata shown on the store page.',
    inputSchema: {
      platform: platformRequired,
      external_id: externalId,
      country: z.string().describe('Country code (e.g. us, tr, de)'),
      language: z.string().describe('Language code (e.g. en-US, tr, de-DE)'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet(`/apps/${args.platform}/${args.external_id}/listing`, { country: args.country, language: args.language });
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
