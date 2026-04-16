import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { registerDashboardTools } from './tools/dashboard.js';
import { registerAppTools } from './tools/apps.js';
import { registerCompetitorTools } from './tools/competitors.js';
import { registerReviewTools } from './tools/reviews.js';
import { registerChangeTools } from './tools/changes.js';
import { registerChartTools } from './tools/charts.js';
import { registerExplorerTools } from './tools/explorer.js';
import { registerPublisherTools } from './tools/publishers.js';
import { registerMetaTools } from './tools/meta.js';

export function registerAllTools(server: McpServer) {
  registerDashboardTools(server);
  registerAppTools(server);
  registerCompetitorTools(server);
  registerReviewTools(server);
  registerChangeTools(server);
  registerChartTools(server);
  registerExplorerTools(server);
  registerPublisherTools(server);
  registerMetaTools(server);
}
