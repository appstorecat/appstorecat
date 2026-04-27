import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet, apiSend, buildPath } from '../client.js';
import { ExternalId, Platform } from './_schemas.js';

export function registerAppTools(server: McpServer): void {
  server.registerTool(
    'list_tracked_apps',
    {
      description:
        'List apps tracked by the authenticated user. ' +
        'Returns { id (internal app_id), platform, external_id, display_name, publisher, category, ... }. ' +
        'Use { platform, external_id } with: get_app, get_app_listing, get_app_sync_status, ' +
        'list_app_competitors, get_app_keywords, get_app_rankings, get_rating_summary. ' +
        'Use internal `id` as `app_id` with: list_app_changes, list_competitor_changes.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform.optional(),
        search: z.string().optional(),
      },
    },
    async ({ platform, search }) => {
      return apiGet('/apps', { platform, search });
    },
  );

  server.registerTool(
    'search_store_apps',
    {
      description:
        'Search the live App Store / Google Play by keyword. ' +
        'Returns { platform, external_id, name, is_tracked, publisher, category, ... }. ' +
        '`country_code` values come from list_countries. ' +
        'Use { platform, external_id } of each result with: get_app, get_app_listing.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        term: z.string().min(1),
        platform: Platform,
        country_code: z.string().optional(),
        exclude_external_ids: z.array(z.string()).optional(),
      },
    },
    async ({ term, platform, country_code, exclude_external_ids }) => {
      return apiGet('/apps/search', {
        term,
        platform,
        country_code,
        'exclude_external_ids': exclude_external_ids,
      });
    },
  );

  server.registerTool(
    'get_app',
    {
      description:
        'Get full app metadata including publisher, category, latest version, rating, unavailable countries, ' +
        'and (when tracked) competitors. ' +
        'Use `publisher.external_id` + `platform` with: get_publisher, get_publisher_store_apps. ' +
        'Use `category.id` with: get_charts, browse_screenshots, browse_icons. ' +
        'Use `versions[].id` with: get_app_keywords.version_id, compare_app_keywords.version_ids.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath('/apps/{platform}/{externalId}', {
        platform,
        externalId: external_id,
      });
      return apiGet(path);
    },
  );

  server.registerTool(
    'get_app_listing',
    {
      description:
        'Get localized store listing (title, subtitle, description, whats_new, screenshots, price) for a country/locale. ' +
        '`country_code` values come from list_countries (required). `locale` is required by the API. ' +
        'Returns `version_id` — use with: get_app_keywords.version_id, compare_app_keywords.version_ids.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
        country_code: z.string(),
        locale: z.string(),
      },
    },
    async ({ platform, external_id, country_code, locale }) => {
      const path = buildPath('/apps/{platform}/{externalId}/listing', {
        platform,
        externalId: external_id,
      });
      return apiGet(path, { country_code, locale });
    },
  );

  server.registerTool(
    'get_app_sync_status',
    {
      description:
        'Check sync progress for an app (status, current_step, progress, elapsed_ms). ' +
        'Use to decide whether downstream data (rankings, listings, changes) is fresh. ' +
        'Read-only; triggering a sync is done via the web UI.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath('/apps/{platform}/{externalId}/sync-status', {
        platform,
        externalId: external_id,
      });
      return apiGet(path);
    },
  );

  server.registerTool(
    'track_app',
    {
      description:
        'Track an app on the authenticated user\'s watchlist. ' +
        'If the app is not yet in the database, the server resolves it from the App Store / Google Play and creates it. ' +
        'After tracking, the app is reachable via list_tracked_apps and a sync is queued automatically. ' +
        'To get an internal `id` for use with add_competitor, follow up with get_app.',
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath('/apps/{platform}/{externalId}/track', {
        platform,
        externalId: external_id,
      });
      return apiSend('POST', path);
    },
  );

  server.registerTool(
    'untrack_app',
    {
      description:
        'Remove an app from the authenticated user\'s watchlist. ' +
        'Also deletes the user\'s competitor relationships involving this app. ' +
        'The underlying app row is preserved (other users may still track it).',
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath('/apps/{platform}/{externalId}/track', {
        platform,
        externalId: external_id,
      });
      return apiSend('DELETE', path);
    },
  );
}
