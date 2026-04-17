import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { registerMetaTools } from './tools/meta.js';

export function registerAllTools(server: McpServer) {
  registerMetaTools(server);
}
