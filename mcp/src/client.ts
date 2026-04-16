const API_URL = process.env.APPSTORECAT_API_URL || 'http://localhost:7460/api/v1';
const API_TOKEN = process.env.APPSTORECAT_API_TOKEN || '';

if (!API_TOKEN) {
  console.error('APPSTORECAT_API_TOKEN environment variable is required');
  process.exit(1);
}

export async function apiGet(path: string, params?: Record<string, unknown>) {
  const url = new URL(`${API_URL}${path}`);
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined) {
        url.searchParams.set(key, String(value));
      }
    }
  }

  const res = await fetch(url.toString(), {
    headers: {
      Authorization: `Bearer ${API_TOKEN}`,
      Accept: 'application/json',
    },
  });

  if (!res.ok) {
    const body = await res.text().catch(() => '');
    throw new Error(`API error ${res.status}: ${body}`);
  }

  return res.json();
}
