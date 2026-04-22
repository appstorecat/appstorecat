import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { registerAppTools } from './tools/apps.js';
import { registerChangeTools } from './tools/changes.js';
import { registerChartTools } from './tools/charts.js';
import { registerCompetitorTools } from './tools/competitors.js';
import { registerDashboardTools } from './tools/dashboard.js';
import { registerExplorerTools } from './tools/explorer.js';
import { registerKeywordTools } from './tools/keywords.js';
import { registerPublisherTools } from './tools/publishers.js';
import { registerRatingTools } from './tools/ratings.js';
import { registerReferenceTools } from './tools/reference.js';

export function registerAllTools(server: McpServer): void {
  registerReferenceTools(server);
  registerAppTools(server);
  registerCompetitorTools(server);
  registerChangeTools(server);
  registerChartTools(server);
  registerRatingTools(server);
  registerKeywordTools(server);
  registerPublisherTools(server);
  registerExplorerTools(server);
  registerDashboardTools(server);
}
