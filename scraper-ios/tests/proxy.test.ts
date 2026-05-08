import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const ENV_KEYS = [
  "HTTPS_PROXY",
  "HTTP_PROXY",
  "https_proxy",
  "http_proxy",
] as const;

function snapshotProxyEnv(): Record<string, string | undefined> {
  return Object.fromEntries(ENV_KEYS.map((k) => [k, process.env[k]]));
}

function restoreProxyEnv(snap: Record<string, string | undefined>) {
  for (const key of ENV_KEYS) {
    if (snap[key] === undefined) {
      delete process.env[key];
    } else {
      process.env[key] = snap[key];
    }
  }
}

describe("redactProxyUrl", () => {
  it("strips user:pass@ from proxy URLs", async () => {
    const { redactProxyUrl } = await import("../src/proxy.js");
    expect(redactProxyUrl("http://alice:s3cret@proxy.example.com:8080")).toBe(
      "http://***@proxy.example.com:8080"
    );
  });

  it("leaves URLs without credentials untouched", async () => {
    const { redactProxyUrl } = await import("../src/proxy.js");
    expect(redactProxyUrl("http://proxy.example.com:8080")).toBe(
      "http://proxy.example.com:8080"
    );
  });

  it("redacts credentials anywhere in a longer message", async () => {
    const { redactProxyUrl } = await import("../src/proxy.js");
    const msg =
      "tunneling failed via http://user:pass@proxy.example.com:8080 (ECONNREFUSED)";
    expect(redactProxyUrl(msg)).toBe(
      "tunneling failed via http://***@proxy.example.com:8080 (ECONNREFUSED)"
    );
  });
});

describe("redactErrorMessage", () => {
  it("redacts an Error instance message", async () => {
    const { redactErrorMessage } = await import("../src/proxy.js");
    const err = new Error("connect failed http://u:p@host:9 ECONNREFUSED");
    expect(redactErrorMessage(err)).toBe(
      "connect failed http://***@host:9 ECONNREFUSED"
    );
  });

  it("handles non-Error values", async () => {
    const { redactErrorMessage } = await import("../src/proxy.js");
    expect(redactErrorMessage(null)).toBe("");
    expect(redactErrorMessage("plain string")).toBe("plain string");
    expect(redactErrorMessage({ msg: "obj" })).toBe("[object Object]");
  });
});

describe("initProxy", () => {
  let snap: Record<string, string | undefined>;

  beforeEach(() => {
    snap = snapshotProxyEnv();
    for (const k of ENV_KEYS) delete process.env[k];
    vi.resetModules();
  });

  afterEach(() => {
    restoreProxyEnv(snap);
    vi.resetModules();
  });

  it("returns disabled when env is missing", async () => {
    const { initProxy } = await import("../src/proxy.js");
    expect(initProxy(undefined)).toEqual({ enabled: false, host: null });
    expect(process.env.HTTPS_PROXY).toBeUndefined();
  });

  it("returns disabled for an empty string", async () => {
    const { initProxy } = await import("../src/proxy.js");
    expect(initProxy("   ")).toEqual({ enabled: false, host: null });
    expect(process.env.HTTPS_PROXY).toBeUndefined();
  });

  it("mirrors the URL into HTTPS_PROXY/HTTP_PROXY", async () => {
    const { initProxy } = await import("../src/proxy.js");
    const status = initProxy("http://proxy.example.com:8080");
    expect(status).toEqual({
      enabled: true,
      host: "proxy.example.com:8080",
    });
    expect(process.env.HTTPS_PROXY).toBe("http://proxy.example.com:8080");
    expect(process.env.HTTP_PROXY).toBe("http://proxy.example.com:8080");
    expect(process.env.https_proxy).toBe("http://proxy.example.com:8080");
    expect(process.env.http_proxy).toBe("http://proxy.example.com:8080");
  });

  it("preserves credentials in the env var (real fetch needs them)", async () => {
    const { initProxy } = await import("../src/proxy.js");
    const status = initProxy("http://alice:s3cret@proxy.example.com:8080");
    expect(status.enabled).toBe(true);
    expect(status.host).toBe("proxy.example.com:8080");
    expect(process.env.HTTPS_PROXY).toBe(
      "http://alice:s3cret@proxy.example.com:8080"
    );
  });

  it("rejects an unparseable URL with redacted credentials", async () => {
    const { initProxy } = await import("../src/proxy.js");
    expect(() =>
      initProxy("not a url with creds user:pass@host but no scheme")
    ).toThrow(/IOS_PROXY_URL is not a valid URL/);
  });

  it("installs an undici global dispatcher when enabled", async () => {
    const { initProxy } = await import("../src/proxy.js");
    const undici = await import("undici");
    const before = undici.getGlobalDispatcher();
    initProxy("http://proxy.example.com:8080");
    const after = undici.getGlobalDispatcher();
    expect(after).not.toBe(before);
    expect(after).toBeInstanceOf(undici.EnvHttpProxyAgent);
  });
});
