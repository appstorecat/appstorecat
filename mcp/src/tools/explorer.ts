import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

export function registerExplorerTools(server: McpServer) {
  server.registerTool('explore_screenshots', {
    description:
      'Browse app screenshots from tracked apps. Returns screenshot URLs grouped by app.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).optional().describe('Platform filter: ios or android'),
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
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/explorer/icons', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
