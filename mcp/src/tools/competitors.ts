import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { apiGet, apiSend, buildPath } from '../client.js';
import { ExternalId, Platform } from './_schemas.js';

const Relationship = z.enum(['direct', 'indirect', 'aspiration']);

export function registerCompetitorTools(server: McpServer): void {
  server.registerTool(
    'list_app_competitors',
    {
      description:
        'List competitor apps for a tracked app. Each competitor exposes { platform, external_id }. ' +
        'Recurse with get_app, get_app_listing, or list_app_competitors (second-degree competitors).',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
      },
    },
    async ({ platform, external_id }) => {
      const path = buildPath('/apps/{platform}/{externalId}/competitors', {
        platform,
        externalId: external_id,
      });
      return apiGet(path);
    },
  );

  server.registerTool(
    'list_all_competitors',
    {
      description:
        'List competitors grouped by parent app across all of the user\'s tracked apps. ' +
        'Returns { parent, competitors[] } groups — both sides carry { platform, external_id } usable with get_app.',
      annotations: { readOnlyHint: true },
      inputSchema: {
        platform: Platform.optional(),
        search: z.string().optional(),
      },
    },
    async ({ platform, search }) => {
      return apiGet('/competitors', { platform, search });
    },
  );

  server.registerTool(
    'add_competitor',
    {
      description:
        'Add a competitor to a tracked app. The parent app (`platform` + `external_id`) MUST already be tracked by the caller — ' +
        'use track_app first. `competitor_app_id` is the INTERNAL numeric `id` of the competitor app, not its external_id; ' +
        'obtain it by tracking the competitor (track_app) and then calling get_app — the response includes `id`. ' +
        'Returns the new competitor record (with internal `id` usable as `competitor_id` for remove_competitor).',
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
        competitor_app_id: z.number().int().positive(),
        relationship: Relationship.optional(),
      },
    },
    async ({ platform, external_id, competitor_app_id, relationship }) => {
      const path = buildPath('/apps/{platform}/{externalId}/competitors', {
        platform,
        externalId: external_id,
      });
      const body: Record<string, unknown> = { competitor_app_id };
      if (relationship) body.relationship = relationship;
      return apiSend('POST', path, body);
    },
  );

  server.registerTool(
    'remove_competitor',
    {
      description:
        'Remove a competitor relationship from a tracked app. `competitor_id` is the relationship row\'s `id` ' +
        '(returned by list_app_competitors / list_all_competitors / add_competitor), not the competitor app\'s id.',
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true },
      inputSchema: {
        platform: Platform,
        external_id: ExternalId,
        competitor_id: z.number().int().positive(),
      },
    },
    async ({ platform, external_id, competitor_id }) => {
      const path = buildPath('/apps/{platform}/{externalId}/competitors/{competitor}', {
        platform,
        externalId: external_id,
        competitor: String(competitor_id),
      });
      return apiSend('DELETE', path);
    },
  );
}
