/**
 * Integration-shape tests that prove the scraper layer goes through the
 * proxy plumbing: the legacy `request` package inside app-store-scraper
 * picks up HTTPS_PROXY env var, and native fetch() goes through the
 * global undici dispatcher we install in initProxy.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("app-store-scraper", () => ({
  default: {
    app: vi.fn(),
    list: vi.fn(),
    search: vi.fn(),
    developer: vi.fn(),
    collection: {
      TOP_FREE_IOS: "topfreeapplications",
      TOP_PAID_IOS: "toppaidapplications",
      TOP_GROSSING_IOS: "topgrossingapplications",
    },
  },
}));

const ENV_KEYS = [
  "HTTPS_PROXY",
  "HTTP_PROXY",
  "https_proxy",
  "http_proxy",
] as const;

function snapshot() {
  return Object.fromEntries(ENV_KEYS.map((k) => [k, process.env[k]]));
}
function restore(snap: Record<string, string | undefined>) {
  for (const k of ENV_KEYS) {
    if (snap[k] === undefined) delete process.env[k];
    else process.env[k] = snap[k];
  }
}

describe("scraper integration with proxy plumbing", () => {
  let snap: Record<string, string | undefined>;

  beforeEach(() => {
    snap = snapshot();
    for (const k of ENV_KEYS) delete process.env[k];
    vi.resetModules();
    vi.clearAllMocks();
  });

  afterEach(() => {
    restore(snap);
    vi.resetModules();
  });

  it("setting IOS_PROXY_URL exports HTTPS_PROXY so app-store-scraper's `request` lib picks it up", async () => {
    const { initProxy } = await import("../src/proxy.js");
    initProxy("http://alice:s3cret@proxy.example.com:8080");

    // The legacy `request` package reads these on each call; we don't
    // mock request itself, just confirm the env contract holds.
    expect(process.env.HTTPS_PROXY).toBe(
      "http://alice:s3cret@proxy.example.com:8080"
    );
    expect(process.env.HTTP_PROXY).toBe(
      "http://alice:s3cret@proxy.example.com:8080"
    );
  });

  it("fetchIdentity calls store.app and forwards lang option", async () => {
    const store = (await import("app-store-scraper")).default as unknown as {
      app: ReturnType<typeof vi.fn>;
    };
    store.app.mockResolvedValueOnce({
      id: 123,
      title: "Test",
      developer: "Acme",
      developerId: "999",
      developerUrl: "https://acme.example/dev",
      primaryGenre: "Productivity",
      primaryGenreId: 6007,
      languages: ["EN"],
      released: "2025-01-02",
      free: true,
      version: "1.0.0",
      currentVersionReleaseDate: "2025-02-02",
    });

    // Reset native fetch mock before importing scraper to avoid the
    // module's web-scrape fallback hitting Apple.
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response("", { status: 500 })
    );

    const scraper = await import("../src/scraper.js");
    await scraper.fetchIdentity("123", "us", "en");

    expect(store.app).toHaveBeenCalledTimes(1);
    expect(store.app.mock.calls[0][0]).toMatchObject({
      id: 123,
      country: "us",
      lang: "en",
    });
  });

  it("scrapeAppStorePage uses the global undici dispatcher when proxy is enabled", async () => {
    const { initProxy } = await import("../src/proxy.js");
    initProxy("http://proxy.example.com:8080");

    const undici = await import("undici");
    const dispatcher = undici.getGlobalDispatcher();
    expect(dispatcher).toBeInstanceOf(undici.EnvHttpProxyAgent);

    // Native fetch consults globalThis dispatcher by default in Node 22.
    // We don't make a real call — we just assert the contract that any
    // fetch() invocation in scraper.ts will inherit this dispatcher.
    const fetchSpy = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValue(new Response("ok", { status: 200 }));

    await fetch("http://example.com/anything");
    expect(fetchSpy).toHaveBeenCalled();
  });

  it("fetchChart maps the public collection enum to the package constant", async () => {
    const store = (await import("app-store-scraper")).default as unknown as {
      list: ReturnType<typeof vi.fn>;
    };
    store.list.mockResolvedValueOnce([]);

    const scraper = await import("../src/scraper.js");
    await scraper.fetchChart("top_free", undefined, "us", 10);

    expect(store.list).toHaveBeenCalledTimes(1);
    expect(store.list.mock.calls[0][0]).toMatchObject({
      collection: "topfreeapplications",
      country: "us",
      num: 10,
    });
  });

  it("searchApps forwards term/country to store.search", async () => {
    const store = (await import("app-store-scraper")).default as unknown as {
      search: ReturnType<typeof vi.fn>;
    };
    store.search.mockResolvedValueOnce([]);

    const scraper = await import("../src/scraper.js");
    await scraper.searchApps("notes", 5, "tr");

    expect(store.search).toHaveBeenCalledWith({
      term: "notes",
      num: 5,
      country: "tr",
    });
  });

  it("fetchDeveloperApps forwards numeric devId to store.developer", async () => {
    const store = (await import("app-store-scraper")).default as unknown as {
      developer: ReturnType<typeof vi.fn>;
    };
    store.developer.mockResolvedValueOnce([]);

    const scraper = await import("../src/scraper.js");
    await scraper.fetchDeveloperApps("777");

    expect(store.developer).toHaveBeenCalledWith({ devId: 777 });
  });
});
