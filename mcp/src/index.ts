#!/usr/bin/env node
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { registerAllTools } from './register.js';

const server = new McpServer(
  {
    name: 'appstorecat',
    version: '1.0.0',
  },
  {
    instructions:
      'AppStoreCat — App Store & Google Play intelligence toolkit. ' +
      'Search apps, track competitors, monitor store listing changes, ' +
      'analyze user reviews, explore trending charts, and discover publishers. ' +
      'All tools are read-only.',
  },
);

registerAllTools(server);

const transport = new StdioServerTransport();
await server.connect(transport);
