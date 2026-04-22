const API_URL = process.env.APPSTORECAT_API_URL || 'http://localhost:7460/api/v1';
const API_TOKEN = process.env.APPSTORECAT_API_TOKEN || '';

if (!API_TOKEN) {
  console.error('APPSTORECAT_API_TOKEN environment variable is required');
  process.exit(1);
}

export type QueryValue =
  | string
  | number
  | boolean
  | ReadonlyArray<string | number>
  | { readonly [key: string]: string | number }
  | null
  | undefined;

export type QueryParams = Readonly<Record<string, QueryValue>>;

export type ToolResult = {
  content: Array<{ type: 'text'; text: string }>;
  isError?: boolean;
};

export function buildPath(
  template: string,
  params: Readonly<Record<string, string>>,
): string {
  return template.replace(/\{(\w+)\}/g, (_, key: string) => {
    const value = params[key];
    if (value === undefined) {
      throw new Error(`Missing path parameter: ${key}`);
    }
    return encodeURIComponent(value);
  });
}

function appendQueryValue(url: URL, key: string, value: QueryValue): void {
  if (value === undefined || value === null) {
    return;
  }

  if (Array.isArray(value)) {
    for (const item of value) {
      if (item === undefined || item === null) continue;
      url.searchParams.append(`${key}[]`, String(item));
    }
    return;
  }

  if (typeof value === 'object') {
    for (const [k, v] of Object.entries(value)) {
      if (v === undefined || v === null) continue;
      url.searchParams.set(`${key}[${k}]`, String(v));
    }
    return;
  }

  url.searchParams.set(key, String(value));
}

export async function apiGet(
  path: string,
  params?: QueryParams,
): Promise<ToolResult> {
  const url = new URL(`${API_URL}${path}`);
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      appendQueryValue(url, key, value as QueryValue);
    }
  }

  let res: Response;
  try {
    res = await fetch(url.toString(), {
      headers: {
        Authorization: `Bearer ${API_TOKEN}`,
        Accept: 'application/json',
      },
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    return {
      content: [{ type: 'text', text: `Network error: ${message}` }],
      isError: true,
    };
  }

  const bodyText = await res.text();

  if (!res.ok) {
    return {
      content: [
        { type: 'text', text: `API error ${res.status}: ${bodyText}` },
      ],
      isError: true,
    };
  }

  // Pass server JSON through unchanged so downstream tools can read IDs
  // (app_id, external_id, version_id, category_id, publisher.external_id, …).
  return {
    content: [{ type: 'text', text: bodyText }],
  };
}
