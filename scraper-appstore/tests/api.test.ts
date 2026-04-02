/**
 * Tests for App Store API endpoints.
 */

import { describe, it, expect, vi, beforeAll } from "vitest";

// Mock app-store-scraper before importing anything
vi.mock("app-store-scraper", () => ({
  default: {
    app: vi.fn(),
    reviews: vi.fn(),
    search: vi.fn(),
    developer: vi.fn(),
    sort: { RECENT: 1 },
  },
}));

import store from "app-store-scraper";
import Fastify from "fastify";
import type { FastifyInstance } from "fastify";

// We need to import after mocking
const buildApp = async (): Promise<FastifyInstance> => {
  const app = Fastify();

  // Register routes inline for testing (avoid top-level await issues)
  const scraper = await import("../src/scraper.js");

  app.get("/health", async () => ({
    status: "ok",
    service: "scraper-appstore",
  }));

  app.get("/apps/search", async (request) => {
    const { term, limit } = request.query as { term: string; limit?: number };
    const results = await scraper.searchApps(term, limit || 10);
    return { results };
  });

  app.get("/apps/:appId/identity", async (request) => {
    const { appId } = request.params as { appId: string };
    return scraper.fetchIdentity(appId);
  });

  app.get("/apps/:appId/listings", async (request) => {
    const { appId } = request.params as { appId: string };
    const { country } = request.query as { country?: string };
    return scraper.fetchListing(appId, country || "us");
  });

  app.get("/apps/:appId/metrics", async (request) => {
    const { appId } = request.params as { appId: string };
    return scraper.fetchMetrics(appId);
  });

  app.get("/apps/:appId/reviews", async (request) => {
    const { appId } = request.params as { appId: string };
    const { country, page } = request.query as {
      country?: string;
      page?: number;
    };
    return scraper.fetchReviews(appId, country || "us", page || 1);
  });

  app.get("/developers/:developerId/apps", async (request) => {
    const { developerId } = request.params as { developerId: string };
    const apps = await scraper.fetchDeveloperApps(developerId);
    return { apps };
  });

  return app;
};

describe("App Store API", () => {
  let app: FastifyInstance;

  beforeAll(async () => {
    app = await buildApp();
  });

  it("GET /health returns ok", async () => {
    const response = await app.inject({ method: "GET", url: "/health" });
    expect(response.statusCode).toBe(200);
    const body = JSON.parse(response.body);
    expect(body.status).toBe("ok");
    expect(body.service).toBe("scraper-appstore");
  });

  it("GET /apps/:appId/identity returns app details", async () => {
    const mockStore = store as any;
    mockStore.app.mockResolvedValueOnce({
      id: 123456,
      title: "Example App",
      developer: "Example Dev",
      developerId: 789,
      developerUrl: "https://example.com",
      primaryGenre: "Utilities",
      contentRating: "4+",
      languages: ["EN", "TR"],
      released: "2024-01-01",
      free: true,
      url: "https://apps.apple.com/app/id123456",
      version: "2.0.0",
      currentVersionReleaseDate: "2024-06-01",
    });

    const response = await app.inject({
      method: "GET",
      url: "/apps/123456/identity",
    });
    expect(response.statusCode).toBe(200);
    const body = JSON.parse(response.body);
    expect(body.app_id).toBe("123456");
    expect(body.name).toBe("Example App");
    expect(body.publisher_name).toBe("Example Dev");
    expect(body.price_model).toBe("free");
  });

  it("GET /apps/:appId/listings returns store listing", async () => {
    const mockStore = store as any;
    mockStore.app.mockResolvedValueOnce({
      title: "Example App",
      subtitle: "A great app",
      description: "Full description here",
      releaseNotes: "Bug fixes",
      icon: "https://example.com/icon.png",
      screenshots: ["https://example.com/s1.png"],
      ipadScreenshots: ["https://example.com/ipad1.png"],
      videoUrl: null,
    });

    const response = await app.inject({
      method: "GET",
      url: "/apps/123456/listings?country=us",
    });
    expect(response.statusCode).toBe(200);
    const body = JSON.parse(response.body);
    expect(body.title).toBe("Example App");
    expect(body.platform).toBe("ios");
    expect(body.screenshots.length).toBe(2);
    expect(body.screenshots[0].device_type).toBe("iphone");
    expect(body.screenshots[1].device_type).toBe("ipad");
  });

  it("GET /apps/:appId/metrics returns metrics", async () => {
    const mockStore = store as any;
    mockStore.app.mockResolvedValueOnce({
      score: 4.7,
      reviews: 50000,
      histogram: { "5": 35000, "4": 10000, "3": 3000, "2": 1000, "1": 1000 },
      size: "104857600",
    });

    const response = await app.inject({
      method: "GET",
      url: "/apps/123456/metrics",
    });
    expect(response.statusCode).toBe(200);
    const body = JSON.parse(response.body);
    expect(body.rating).toBe(4.7);
    expect(body.rating_count).toBe(50000);
    expect(body.rating_breakdown["5"]).toBe(35000);
    expect(body.file_size_bytes).toBe(104857600);
  });

  it("GET /apps/:appId/reviews returns reviews", async () => {
    const mockStore = store as any;
    mockStore.reviews.mockResolvedValueOnce([
      {
        id: "r1",
        userName: "John",
        title: "Great!",
        text: "Love this app",
        score: 5,
        date: "2024-06-01",
        version: "2.0.0",
      },
    ]);

    const response = await app.inject({
      method: "GET",
      url: "/apps/123456/reviews?country=us&page=1",
    });
    expect(response.statusCode).toBe(200);
    const body = JSON.parse(response.body);
    expect(body.reviews.length).toBe(1);
    expect(body.reviews[0].external_id).toBe("r1");
    expect(body.reviews[0].rating).toBe(5);
    expect(body.reviews[0].country_code).toBe("US");
  });

  it("GET /developers/:developerId/apps returns developer apps", async () => {
    const mockStore = store as any;
    mockStore.developer.mockResolvedValueOnce([
      {
        id: 111,
        title: "App One",
        icon: "https://example.com/icon1.png",
        score: 4.2,
        reviews: 500,
        free: true,
        primaryGenre: "Tools",
      },
    ]);

    const response = await app.inject({
      method: "GET",
      url: "/developers/789/apps",
    });
    expect(response.statusCode).toBe(200);
    const body = JSON.parse(response.body);
    expect(body.apps.length).toBe(1);
    expect(body.apps[0].external_id).toBe("111");
  });

  it("GET /apps/search returns search results", async () => {
    const mockStore = store as any;
    mockStore.search.mockResolvedValueOnce([
      {
        id: 123,
        title: "Example App",
        developer: "Example Dev",
        icon: "https://example.com/icon.png",
        score: 4.5,
        price: 0,
        free: true,
      },
    ]);

    const response = await app.inject({
      method: "GET",
      url: "/apps/search?term=example&limit=5",
    });
    expect(response.statusCode).toBe(200);
    const body = JSON.parse(response.body);
    expect(body.results.length).toBe(1);
    expect(body.results[0].app_id).toBe("123");
  });
});
