import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet } from '../client.js';
import { Page, Platform } from './_schemas.js';

const ChangeField = z.enum([
  'title',
  'subtitle',
  'description',
  'whats_new',
  'screenshots',
  'locale_added',
  'locale_removed',
]);

export function registerChangeTools(server: McpServer): void {
  server.registerTool(
    'list_app_changes',
    {
      description:
        'List detected store-listing changes for the user\'s tracked apps. ' +
        'Each change exposes { version_id, field_changed, old_value, new_value, detected_at }. ' +
        '`app_id` filter is the INTERNAL id from list_tracked_apps (not the store external_id). ' +
        'Use `version_id` with get_app_keywords or compare_app_keywords to correlate keyword impact.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        per_page: z.number().int().optional(),
        page: Page.optional(),
        field: ChangeField.optional(),
        platform: Platform.optional(),
        search: z.string().optional(),
        app_id: z.number().int().min(1).optional(),
      },
    },
    async ({ per_page, page, field, platform, search, app_id }) => {
      return apiGet('/changes/apps', {
        per_page,
        page,
        field,
        platform,
        search,
        app_id,
      });
    },
  );

  server.registerTool(
    'list_competitor_changes',
    {
      description:
        'List detected store-listing changes for competitor apps across the user\'s tracked apps. ' +
        'Same shape as list_app_changes. `app_id` is the INTERNAL parent-app id from list_tracked_apps.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        per_page: z.number().int().optional(),
        page: Page.optional(),
        field: ChangeField.optional(),
        platform: Platform.optional(),
        search: z.string().optional(),
        app_id: z.number().int().min(1).optional(),
      },
    },
    async ({ per_page, page, field, platform, search, app_id }) => {
      return apiGet('/changes/competitors', {
        per_page,
        page,
        field,
        platform,
        search,
        app_id,
      });
    },
  );
}
