import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

export function registerExplorerTools(server: McpServer) {
  server.registerTool('explore_screenshots', {
    description:
      'Browse app screenshots from tracked apps. Returns screenshot URLs grouped by app.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).optional().describe('Platform filter: ios or android'),
      category_id: z.number().optional().describe('Filter by store category ID'),
      search: z.string().optional().describe('Search by app name'),
      per_page: z.number().optional().describe('Results per page (default 12)'),
      page: z.number().optional().describe('Page number for pagination'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/explorer/screenshots', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('explore_icons', {
    description:
      'Browse app icons from tracked apps. Returns icon URLs grouped by app.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).optional().describe('Platform filter: ios or android'),
      category_id: z.number().optional().describe('Filter by store category ID'),
      search: z.string().optional().describe('Search by app name'),
      per_page: z.number().optional().describe('Results per page (default 48)'),
      page: z.number().optional().describe('Page number for pagination'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/explorer/icons', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
