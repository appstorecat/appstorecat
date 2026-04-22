import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { apiGet } from '../client.js';

export function registerDashboardTools(server: McpServer): void {
  server.registerTool(
    'get_dashboard',
    {
      description:
        'Get the user\'s dashboard summary: { total_apps, total_versions, total_changes, recent_changes[] }. ' +
        'Use `recent_changes[].app_id` (INTERNAL id) as `app_id` filter in list_app_changes / list_competitor_changes.',
      annotations: { readOnlyHint: true },
      inputSchema: {},
    },
    async () => {
      return apiGet('/dashboard');
    },
  );
}
