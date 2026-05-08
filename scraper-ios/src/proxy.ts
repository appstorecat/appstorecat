/**
 * Proxy bootstrap for outbound store traffic.
 *
 * Reads IOS_PROXY_URL once at startup. Supports two schemes:
 *
 *   http://[user:pass@]host:port    — HTTP/HTTPS proxy
 *   socks5://[user:pass@]host:port  — SOCKS5 proxy (also socks5h:// alias)
 *
 * For HTTP proxies we mirror the URL into HTTPS_PROXY/HTTP_PROXY env vars
 * so the legacy `request` library inside app-store-scraper picks it up
 * automatically, and we install undici's EnvHttpProxyAgent so native
 * fetch() calls also flow through the proxy.
 *
 * For SOCKS proxies the legacy `request` library has no env-var hook —
 * we expose a `requestOptions` helper that callers thread into every
 * store.* call, and we install undici's Socks5ProxyAgent as the global
 * dispatcher so native fetch() is also routed.
 */

import { SocksProxyAgent } from "socks-proxy-agent";
import {
  EnvHttpProxyAgent,
  Socks5ProxyAgent,
  setGlobalDispatcher,
} from "undici";

export interface ProxyStatus {
  enabled: boolean;
  host: string | null;
  scheme: "http" | "https" | "socks5" | null;
}

/**
 * RequestOptions carried through every app-store-scraper call so the
 * underlying `request` library uses our proxy. For HTTP proxies the
 * `proxy` URL alone is enough; for SOCKS we attach a custom agent.
 */
export interface ProxyRequestOptions {
  proxy?: string;
  agent?: import("http").Agent;
}

let activeRequestOptions: ProxyRequestOptions | null = null;

const SOCKS_SCHEMES = new Set(["socks5:", "socks5h:", "socks:"]);

export function redactProxyUrl(value: string): string {
  return value.replace(/\/\/[^@/\s]+@/g, "//***@");
}

export function redactErrorMessage(value: unknown): string {
  const raw =
    value instanceof Error
      ? value.message
      : typeof value === "string"
        ? value
        : String(value ?? "");
  return redactProxyUrl(raw);
}

function parseUrlOrThrow(url: string): URL {
  try {
    return new URL(url);
  } catch {
    throw new Error(
      `IOS_PROXY_URL is not a valid URL: ${redactProxyUrl(url)}`
    );
  }
}

const PROXY_ENV_KEYS = [
  "HTTPS_PROXY",
  "HTTP_PROXY",
  "https_proxy",
  "http_proxy",
] as const;

function setProxyEnvVars(url: string): void {
  for (const key of PROXY_ENV_KEYS) {
    process.env[key] = url;
  }
}

function clearProxyEnvVars(): void {
  for (const key of PROXY_ENV_KEYS) {
    delete process.env[key];
  }
}

/** Returns the requestOptions snapshot for the active proxy, or null when disabled. */
export function getProxyRequestOptions(): ProxyRequestOptions | null {
  return activeRequestOptions;
}

export function initProxy(rawUrl: string | undefined): ProxyStatus {
  activeRequestOptions = null;

  if (!rawUrl || !rawUrl.trim()) {
    // Drop any env vars a previous call may have left behind so a runtime
    // re-init with an empty URL fully disables proxying.
    clearProxyEnvVars();
    return { enabled: false, host: null, scheme: null };
  }

  const url = rawUrl.trim();
  const parsed = parseUrlOrThrow(url);
  const host = parsed.port
    ? `${parsed.hostname}:${parsed.port}`
    : parsed.hostname;

  if (SOCKS_SCHEMES.has(parsed.protocol)) {
    const agent = new SocksProxyAgent(url);
    activeRequestOptions = { agent };
    // undici's Socks5ProxyAgent only accepts socks5:// or socks:// — it
    // rejects the socks5h:// alias even though it's wire-equivalent.
    // Normalize before handing it off.
    const normalized = url.replace(/^socks5h:\/\//, "socks5://");
    setGlobalDispatcher(new Socks5ProxyAgent(normalized));
    return { enabled: true, host, scheme: "socks5" };
  }

  if (parsed.protocol === "http:" || parsed.protocol === "https:") {
    setProxyEnvVars(url);
    activeRequestOptions = { proxy: url };
    setGlobalDispatcher(new EnvHttpProxyAgent());
    return {
      enabled: true,
      host,
      scheme: parsed.protocol === "https:" ? "https" : "http",
    };
  }

  throw new Error(
    `IOS_PROXY_URL must use http://, https://, or socks5://: ${redactProxyUrl(url)}`
  );
}
