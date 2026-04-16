import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { apiGet } from '../client.js';

export function registerMetaTools(server: McpServer) {
  server.registerTool('list_countries', {
    description: 'List all supported countries/regions for app store data.',
    annotations: { readOnlyHint: true },
  }, async () => {
    const data = await apiGet('/countries');
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool('list_store_categories', {
    description:
      'List all app store categories (games, productivity, etc.) for both App Store and Google Play.',
    annotations: { readOnlyHint: true },
  }, async () => {
    const data = await apiGet('/store-categories');
    return { content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }] };
  });
}
