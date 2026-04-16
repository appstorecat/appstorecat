import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

export function registerCompetitorTools(server: McpServer) {
  server.registerTool('get_app_competitors', {
    description:
      'Get competitors defined for a specific app. Shows which apps have been marked as competitors.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).describe('Platform: ios or android'),
      external_id: z.string().describe('App external ID'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet(`/apps/${args.platform}/${args.external_id}/competitors`);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('list_all_competitors', {
    description: 'List all competitor relationships across all tracked apps.',
    annotations: { readOnlyHint: true },
  }, async () => {
    const data = await apiGet('/competitors');
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
