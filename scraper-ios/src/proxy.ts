/**
 * Proxy bootstrap for outbound store traffic.
 *
 * Reads IOS_PROXY_URL once at startup. When set, mirrors it into HTTPS_PROXY
 * and HTTP_PROXY env vars (so the legacy `request` package inside
 * app-store-scraper picks it up automatically) and installs a global undici
 * dispatcher so native fetch() calls are also proxied.
 */

import { setGlobalDispatcher, EnvHttpProxyAgent } from "undici";

export interface ProxyStatus {
  enabled: boolean;
  host: string | null;
}

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

function parseProxyHost(url: string): string {
  try {
    return new URL(url).host;
  } catch {
    throw new Error(
      `IOS_PROXY_URL is not a valid URL: ${redactProxyUrl(url)}`
    );
  }
}

export function initProxy(rawUrl: string | undefined): ProxyStatus {
  if (!rawUrl || !rawUrl.trim()) {
    return { enabled: false, host: null };
  }

  const url = rawUrl.trim();
  const host = parseProxyHost(url);

  process.env.HTTPS_PROXY = url;
  process.env.HTTP_PROXY = url;
  process.env.https_proxy = url;
  process.env.http_proxy = url;

  setGlobalDispatcher(new EnvHttpProxyAgent());

  return { enabled: true, host };
}
