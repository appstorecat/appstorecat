import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';

export function registerChangeTools(server: McpServer) {
  server.registerTool('get_app_changes', {
    description:
      'Get recent store listing changes for tracked apps. Shows what changed (description, screenshots, version, etc.) and when.',
    inputSchema: {
      field: z.enum(['title', 'subtitle', 'description', 'whats_new', 'screenshots', 'locale_removed']).optional().describe('Filter by changed field'),
      per_page: z.number().optional().describe('Results per page (default 25)'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/changes/apps', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('get_competitor_changes', {
    description:
      'Get recent store listing changes for competitor apps. Same as app changes but for competitors.',
    inputSchema: {
      field: z.enum(['title', 'subtitle', 'description', 'whats_new', 'screenshots', 'locale_removed']).optional().describe('Filter by changed field'),
      per_page: z.number().optional().describe('Results per page (default 25)'),
    },
    annotations: { readOnlyHint: true },
  }, async (args) => {
    const data = await apiGet('/changes/competitors', args);
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
