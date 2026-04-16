import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

export function registerReviewTools(server: McpServer) {
  server.registerTool('get_app_reviews', {
    description:
      'Get user reviews for an app. Can filter by rating and sort order. Useful for understanding user sentiment and feedback.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).describe('Platform: ios or android'),
      external_id: z.string().describe('App external ID'),
      sort: z.enum(['recent', 'helpful']).optional().describe('Sort order: recent or helpful'),
      rating: z.number().min(1).max(5).optional().describe('Filter by star rating (1-5)'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const { platform, external_id, ...params } = args;
    const data = await apiGet(`/apps/${platform}/${external_id}/reviews`, params);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('get_review_summary', {
    description:
      'Get a summary of app reviews including rating distribution, average rating, and total review count.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).describe('Platform: ios or android'),
      external_id: z.string().describe('App external ID'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet(`/apps/${args.platform}/${args.external_id}/reviews/summary`);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
