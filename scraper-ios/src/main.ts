/**
 * App Store API — Fastify application.
 */

import Fastify, { type FastifyReply } from "fastify";
import fastifySwagger from "@fastify/swagger";
import fastifySwaggerUi from "@fastify/swagger-ui";
import * as scraper from "./scraper.js";

/**
 * Map a scraper exception to an appropriate HTTP response.
 * app-store-scraper throws three shapes:
 *  - Error("App not found (404)") for storefronts where Apple returns 404
 *  - plain object `{ response }` for storefronts Apple blocks upstream (cn, hk)
 *  - anything else (fetch failure, rate limit, parse error) — treat as 500
 */
function sendScraperError(reply: FastifyReply, e: unknown) {
  const rawMessage =
    e instanceof Error
      ? e.message
      : typeof e === "string"
        ? e
        : ((e as { message?: unknown })?.message ?? "");
  const message = typeof rawMessage === "string" ? rawMessage : String(rawMessage);
  const lower = message.toLowerCase();
  const looksLikeNotFound =
    lower.includes("not found") ||
    lower.includes("404") ||
    (!message && e !== null && typeof e === "object");

  if (looksLikeNotFound) {
    return reply
      .status(404)
      .send({ error: message || "App not found in this storefront" });
  }
  return reply.status(500).send({ error: message || "Unknown scraper error" });
}
import {
  AppIdentitySchema,
  AppMetricsSchema,
  ChartResponseSchema,
  DeveloperAppsResponseSchema,
  ErrorResponseSchema,
  HealthResponseSchema,
  LocalizedListingsResponseSchema,
  SearchResponseSchema,
  StoreListingSchema,
} from "./schemas.js";

if (!process.env.PORT) {
  throw new Error("PORT environment variable is required");
}
const PORT = Number(process.env.PORT);

const app = Fastify({ logger: { level: "info" } });

await app.register(fastifySwagger, {
  openapi: {
    info: {
      title: "App Store Scraper API",
      description: "Scraper API for Apple App Store app data",
      version: "1.0.0",
    },
    servers: [{ url: process.env.PUBLIC_URL || `http://localhost:${PORT}` }],
  },
});

await app.register(fastifySwaggerUi, {
  routePrefix: "/docs",
});

// Index
app.get(
  "/",
  { schema: { hide: true } },
  async (_request, reply) => {
    reply.type("text/html").send(`<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>AppStoreCat Scraper - App Store</title>
<style>body{margin:0;background:#000;display:flex;justify-content:center;align-items:center;height:100vh;font-family:system-ui,sans-serif}
a{color:#fff;font-size:1.25rem;text-decoration:none;opacity:.6;transition:opacity .2s}a:hover{opacity:1}</style></head>
<body><a href="https://github.com/appstorecat/appstorecat" target="_blank">github.com/appstorecat/appstorecat</a></body></html>`);
  }
);

// Health check
app.get(
  "/health",
  {
    schema: {
      tags: ["system"],
      response: { 200: HealthResponseSchema },
    },
  },
  async () => ({ status: "ok", service: "scraper-ios" })
);

// Charts / Top lists
app.get(
  "/charts",
  {
    schema: {
      tags: ["charts"],
      summary: "Get top chart rankings",
      querystring: {
        type: "object",
        properties: {
          collection: {
            type: "string",
            enum: ["top_free", "top_paid", "top_grossing"],
          },
          category: { type: "integer", description: "Genre ID (e.g. 6014 for Games)" },
          country: { type: "string", default: "us" },
          num: { type: "integer", minimum: 1, maximum: 200, default: 200 },
        },
        required: ["collection"],
      },
      response: {
        200: ChartResponseSchema,
        500: ErrorResponseSchema,
      },
    },
  },
  async (request, reply) => {
    const { collection, category, country, num } = request.query as {
      collection: string;
      category?: number;
      country?: string;
      num?: number;
    };
    try {
      const results = await scraper.fetchChart(collection, category, country || "us", num || 200);
      return { results };
    } catch (e: unknown) {
      return sendScraperError(reply, e);
    }
  }
);

// Search apps
app.get(
  "/apps/search",
  {
    schema: {
      tags: ["apps"],
      summary: "Search apps on App Store",
      querystring: {
        type: "object",
        properties: {
          term: { type: "string", minLength: 1 },
          limit: { type: "integer", minimum: 1, maximum: 50, default: 10 },
          country: { type: "string", minLength: 2, maxLength: 2, default: "us" },
        },
        required: ["term"],
      },
      response: {
        200: SearchResponseSchema,
        500: ErrorResponseSchema,
      },
    },
  },
  async (request, reply) => {
    const { term, limit, country } = request.query as { term: string; limit?: number; country?: string };
    try {
      const results = await scraper.searchApps(term, limit || 10, country || "us");
      return { results };
    } catch (e: unknown) {
      return sendScraperError(reply, e);
    }
  }
);

// App identity
app.get(
  "/apps/:appId/identity",
  {
    schema: {
      tags: ["apps"],
      summary: "Get app identity and details",
      params: {
        type: "object",
        properties: { appId: { type: "string" } },
        required: ["appId"],
      },
      querystring: {
        type: "object",
        properties: {
          country: { type: "string", default: "us" },
          lang: { type: "string", description: "Language code (e.g. tr, de-DE)" },
        },
      },
      response: {
        200: AppIdentitySchema,
        404: ErrorResponseSchema,
        500: ErrorResponseSchema,
      },
    },
  },
  async (request, reply) => {
    const { appId } = request.params as { appId: string };
    const { country, lang } = request.query as { country?: string; lang?: string };
    try {
      return await scraper.fetchIdentity(appId, country || "us", lang || undefined);
    } catch (e: unknown) {
      return sendScraperError(reply, e);
    }
  }
);

// Store listing
app.get(
  "/apps/:appId/listings",
  {
    schema: {
      tags: ["apps"],
      summary: "Get store listing for a country",
      params: {
        type: "object",
        properties: { appId: { type: "string" } },
        required: ["appId"],
      },
      querystring: {
        type: "object",
        properties: {
          country: { type: "string", default: "us" },
          lang: { type: "string", description: "Language code (e.g. tr, de-DE)" },
        },
      },
      response: {
        200: StoreListingSchema,
        404: ErrorResponseSchema,
        500: ErrorResponseSchema,
      },
    },
  },
  async (request, reply) => {
    const { appId } = request.params as { appId: string };
    const { country, lang } = request.query as { country?: string; lang?: string };
    try {
      return await scraper.fetchListing(appId, country || "us", lang || undefined);
    } catch (e: unknown) {
      return sendScraperError(reply, e);
    }
  }
);

// Localized listings
app.get(
  "/apps/:appId/listings/locales",
  {
    schema: {
      tags: ["apps"],
      summary: "Get store listings for multiple countries",
      params: {
        type: "object",
        properties: { appId: { type: "string" } },
        required: ["appId"],
      },
      querystring: {
        type: "object",
        properties: {
          countries: {
            type: "string",
            description: "Comma-separated country codes (e.g. us,tr,de)",
          },
        },
        required: ["countries"],
      },
      response: {
        200: LocalizedListingsResponseSchema,
        500: ErrorResponseSchema,
      },
    },
  },
  async (request, reply) => {
    const { appId } = request.params as { appId: string };
    const { countries } = request.query as { countries: string };
    try {
      const countryList = countries
        .split(",")
        .map((c) => c.trim())
        .filter(Boolean);
      const listings = await scraper.fetchLocalizedListings(appId, countryList);
      return { listings };
    } catch (e: unknown) {
      return sendScraperError(reply, e);
    }
  }
);

// Metrics
app.get(
  "/apps/:appId/metrics",
  {
    schema: {
      tags: ["apps"],
      summary: "Get app metrics (rating, file size, etc.)",
      params: {
        type: "object",
        properties: { appId: { type: "string" } },
        required: ["appId"],
      },
      querystring: {
        type: "object",
        properties: {
          country: { type: "string", default: "us" },
          lang: { type: "string", description: "Language code (e.g. tr, de-DE)" },
        },
      },
      response: {
        200: AppMetricsSchema,
        404: ErrorResponseSchema,
        500: ErrorResponseSchema,
      },
    },
  },
  async (request, reply) => {
    const { appId } = request.params as { appId: string };
    const { country, lang } = request.query as { country?: string; lang?: string };
    try {
      return await scraper.fetchMetrics(appId, country || "us", lang || undefined);
    } catch (e: unknown) {
      return sendScraperError(reply, e);
    }
  }
);

// Developer apps
app.get(
  "/developers/:developerId/apps",
  {
    schema: {
      tags: ["developers"],
      summary: "Get all apps by a developer",
      params: {
        type: "object",
        properties: { developerId: { type: "string" } },
        required: ["developerId"],
      },
      response: {
        200: DeveloperAppsResponseSchema,
        500: ErrorResponseSchema,
      },
    },
  },
  async (request, reply) => {
    const { developerId } = request.params as { developerId: string };
    try {
      const apps = await scraper.fetchDeveloperApps(developerId);
      return { apps };
    } catch (e: unknown) {
      return sendScraperError(reply, e);
    }
  }
);

// Developer search
app.get(
  "/developers/search",
  {
    schema: {
      tags: ["developers"],
      summary: "Search for developers (searches apps, returns unique developers)",
      querystring: {
        type: "object",
        properties: {
          term: { type: "string", minLength: 1 },
          limit: { type: "integer", minimum: 1, maximum: 50, default: 10 },
          country: { type: "string", minLength: 2, maxLength: 2, default: "us" },
        },
        required: ["term"],
      },
      response: {
        200: SearchResponseSchema,
        500: ErrorResponseSchema,
      },
    },
  },
  async (request, reply) => {
    const { term, limit, country } = request.query as { term: string; limit?: number; country?: string };
    try {
      const results = await scraper.searchApps(term, limit || 10, country || "us");
      return { results };
    } catch (e: unknown) {
      return sendScraperError(reply, e);
    }
  }
);

// Start server
const start = async () => {
  try {
    await app.listen({ port: PORT, host: "0.0.0.0" });
  } catch (err) {
    app.log.error(err);
    process.exit(1);
  }
};

start();

export { app };
