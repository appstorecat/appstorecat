import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

export function registerChartTools(server: McpServer) {
  server.registerTool('get_charts', {
    description:
      'Get trending/top charts from App Store and Google Play. Shows which apps are currently trending or top-ranked. Use list_store_categories first to find the category_id.',
    inputSchema: {
      collection: z.enum(['top_free', 'top_paid', 'top_grossing']).describe('Chart collection type: top_free, top_paid, or top_grossing'),
      platform: z.enum(['ios', 'android']).optional().describe('Platform filter: ios or android'),
      country: z.string().optional().describe('Country code (e.g. us, tr, de)'),
      category_id: z.number().optional().describe('Store category ID. Use list_store_categories to find the ID'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/charts', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
