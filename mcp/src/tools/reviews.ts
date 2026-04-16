import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

export function registerReviewTools(server: McpServer) {
  server.registerTool('get_app_reviews', {
    description:
      'Get user reviews for an app. Can filter by rating, country, and sort order. Useful for understanding user sentiment and feedback.',
    inputSchema: {
      platform: z.enum(['ios', 'android']).describe('Platform: ios or android'),
      external_id: z.string().describe('App external ID'),
      sort: z.enum(['latest', 'oldest', 'highest', 'lowest']).optional().describe('Sort order: latest, oldest, highest, or lowest rated'),
      rating: z.number().min(1).max(5).optional().describe('Filter by star rating (1-5)'),
      country_code: z.string().optional().describe('Country code filter (e.g. US, TR, DE)'),
      per_page: z.number().optional().describe('Results per page (default 25)'),
      page: z.number().optional().describe('Page number for pagination'),
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
      country_code: z.string().optional().describe('Country code filter (e.g. US, TR, DE)'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const { platform, external_id, ...params } = args;
    const data = await apiGet(`/apps/${platform}/${external_id}/reviews/summary`, params);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
