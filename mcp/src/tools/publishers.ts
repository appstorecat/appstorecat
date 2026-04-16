import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

export function registerPublisherTools(server: McpServer) {
  server.registerTool('search_publishers', {
    description:
      'Search for app publishers/developers on App Store or Google Play.',
    inputSchema: {
      term: z.string().describe('Search keyword for publisher name'),
      platform: z.enum(['ios', 'android']).describe('Platform: ios or android'),
      country: z.string().optional().describe('Country code (e.g. us, tr, de). Defaults to us'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/publishers/search', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('list_publishers', {
    description: 'List known publishers/developers with their app counts.',
    inputSchema: {
      page: z.number().optional().describe('Page number for pagination'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/publishers', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('get_publisher', {
    description: 'Get detailed information about a specific publisher/developer.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).describe('Platform: ios or android'),
      external_id: z.string().describe('Publisher external ID'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet(`/publishers/${args.platform}/${args.external_id}`);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('get_publisher_apps', {
    description:
      'Get all apps published by a specific publisher/developer on the store.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).describe('Platform: ios or android'),
      external_id: z.string().describe('Publisher external ID'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet(`/publishers/${args.platform}/${args.external_id}/store-apps`);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
