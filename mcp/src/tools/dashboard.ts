import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { apiGet } from '../client.js';

export function registerDashboardTools(server: McpServer) {
  server.registerTool('get_dashboard', {
    description:
      'Get dashboard overview with tracked app count, recent changes, recent reviews, and build status summary',
    annotations: { readOnlyHint: true },
  }, async () => {
    const data = await apiGet('/dashboard');
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
