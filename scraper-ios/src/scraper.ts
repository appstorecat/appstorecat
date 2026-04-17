/**
 * App Store scraper wrapper using app-store-scraper package + web scraping fallback.
 */

// @ts-expect-error - app-store-scraper has no type definitions
import store from "app-store-scraper";
import type {
  AppIdentity,
  AppMetrics,
  AppReview,
  ChartEntry,
  DeveloperApp,
  Screenshot,
  SearchResult,
  StoreListing,
} from "./schemas.js";

/**
 * Scrape App Store web page for data not available via the scraper package
 * (subtitle, screenshots, video, rating breakdown).
 */
async function scrapeAppStorePage(
  trackId: string,
  country: string = "us"
): Promise<{
  subtitle: string | null;
  screenshots: Screenshot[];
  video_url: string | null;
  rating_breakdown: Record<string, number> | null;
}> {
  const result = {
    subtitle: null as string | null,
    screenshots: [] as Screenshot[],
    video_url: null as string | null,
    rating_breakdown: null as Record<string, number> | null,
  };

  try {
    const url = `https://apps.apple.com/${country}/app/id${trackId}`;
    const response = await fetch(url, {
      headers: {
        "User-Agent":
          "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36",
        "Accept-Language": "en-US,en;q=0.9",
      },
    });

    if (!response.ok) return result;

    const html = await response.text();
    const match = html.match(
      /<script[^>]*id="serialized-server-data"[^>]*>(.+?)<\/script>/s
    );
    if (!match) return result;

    const serverData = JSON.parse(match[1].trim());
    if (!serverData) return result;

    const appData =
      serverData?.data?.[0]?.data ?? serverData?.[0]?.data ?? {};
    if (!appData || Object.keys(appData).length === 0) return result;

    // Subtitle
    result.subtitle = appData?.lockup?.subtitle ?? null;

    const mapping = appData?.shelfMapping ?? {};
    let order = 0;

    // Phone screenshots
    for (const item of mapping?.["product_media_phone_"]?.items ?? []) {
      if (item?.screenshot?.template) {
        const screenshotUrl = item.screenshot.template
          .replace("{w}", "1290")
          .replace("{h}", "2796")
          .replace("{c}", "bb")
          .replace("{f}", "jpg");
        result.screenshots.push({
          url: screenshotUrl,
          device_type: "iphone",
          order: order++,
        });
      }
      if (item?.video?.videoUrl && !result.video_url) {
        result.video_url = item.video.videoUrl;
      }
    }

    // iPad screenshots
    for (const item of mapping?.["product_media_pad_"]?.items ?? []) {
      if (item?.screenshot?.template) {
        const screenshotUrl = item.screenshot.template
          .replace("{w}", "2048")
          .replace("{h}", "2732")
          .replace("{c}", "bb")
          .replace("{f}", "jpg");
        result.screenshots.push({
          url: screenshotUrl,
          device_type: "ipad",
          order: order++,
        });
      }
    }

    // Rating breakdown
    const ratingsItems = mapping?.productRatings?.items ?? [];
    if (ratingsItems.length > 0) {
      const counts = ratingsItems[0]?.ratingCounts;
      if (counts && counts.length === 5) {
        result.rating_breakdown = {
          "5": counts[0],
          "4": counts[1],
          "3": counts[2],
          "2": counts[3],
          "1": counts[4],
        };
      }
    }
  } catch {
    // Silently fail — web scraping is a best-effort fallback
  }

  return result;
}

export async function fetchIdentity(appId: string, country: string = "us", lang?: string): Promise<AppIdentity> {
  const opts: Record<string, any> = { id: Number(appId), country };
  if (lang) opts.lang = lang;
  const info = await store.app(opts);

  return {
    app_id: String(info.id),
    name: info.title || "",
    publisher_name: info.developer || "",
    publisher_external_id: info.developerId ? String(info.developerId).split("?")[0] : null,
    publisher_url: info.developerUrl || null,
    category: info.primaryGenre || "",
    category_id: info.primaryGenreId != null ? String(info.primaryGenreId) : null,
    content_rating: info.contentRating || null,
    supported_locales: info.languages || null,
    original_release_date: info.released
      ? new Date(info.released).toISOString().slice(0, 10)
      : null,
    price_model: info.free ? "free" : "paid",
    price: info.price ?? 0,
    currency: info.currency ?? null,
    store_url: info.url || null,
    version: info.version || `ag.${new Date().toISOString().slice(0, 10).replace(/-/g, '')}`,
    current_version_release_date: info.currentVersionReleaseDate
      ? new Date(info.currentVersionReleaseDate).toISOString().slice(0, 10)
      : new Date().toISOString().slice(0, 10),
  };
}

export async function fetchListing(
  appId: string,
  country: string = "us",
  lang?: string
): Promise<StoreListing> {
  const opts: Record<string, any> = { id: Number(appId), country };
  if (lang) opts.lang = lang;
  const [info, webData] = await Promise.all([
    store.app(opts),
    scrapeAppStorePage(appId, country),
  ]);

  const screenshots: Screenshot[] = [];
  let order = 0;

  const iphoneUrls = info.screenshots || [];
  for (const url of iphoneUrls) {
    screenshots.push({ url, device_type: "iphone", order: order++ });
  }

  // If package returned no iPhone screenshots, use web-scraped ones
  if (iphoneUrls.length === 0) {
    for (const s of webData.screenshots.filter((s: { device_type: string }) => s.device_type === "iphone")) {
      screenshots.push({ ...s, order: order++ });
    }
  }

  for (const url of info.ipadScreenshots || []) {
    screenshots.push({ url, device_type: "ipad", order: order++ });
  }

  const finalScreenshots = screenshots;

  const description = info.description || "";

  return {
    platform: "ios",
    locale: lang || country,
    title: info.title || "",
    subtitle: info.subtitle || webData.subtitle || null,
    short_description: null,
    description,
    promotional_text: info.promotionalText || null,
    whats_new: info.releaseNotes || "Bug fixes and performance improvements.",
    icon_url: info.icon || null,
    screenshots: finalScreenshots,
    video_url: info.videoUrl || webData.video_url || null,
    description_length: description.length,
    price: info.price ?? 0,
    currency: info.currency ?? null,
  };
}

export async function fetchLocalizedListings(
  appId: string,
  countries: string[]
): Promise<StoreListing[]> {
  const listings: StoreListing[] = [];

  for (const country of countries) {
    try {
      const listing = await fetchListing(appId, country);
      listings.push(listing);
    } catch {
      continue;
    }
  }

  return listings;
}

export async function fetchMetrics(appId: string, country: string = "us", lang?: string): Promise<AppMetrics> {
  const opts: Record<string, any> = { id: Number(appId), country };
  if (lang) opts.lang = lang;
  const [info, webData] = await Promise.all([
    store.app(opts),
    scrapeAppStorePage(appId, country),
  ]);

  let ratingBreakdown: Record<string, number> | null = null;
  if (info.histogram) {
    ratingBreakdown = {
      "5": info.histogram["5"] || 0,
      "4": info.histogram["4"] || 0,
      "3": info.histogram["3"] || 0,
      "2": info.histogram["2"] || 0,
      "1": info.histogram["1"] || 0,
    };
  }

  // Use web-scraped breakdown if package didn't provide one
  if (!ratingBreakdown && webData.rating_breakdown) {
    ratingBreakdown = webData.rating_breakdown;
  }

  return {
    rating: info.score || 0,
    rating_count: info.reviews || 0,
    rating_breakdown: ratingBreakdown,
    installs_range: null,
    file_size_bytes: info.size ? Number(info.size) : null,
  };
}

export async function fetchReviews(
  appId: string,
  country: string = "us",
  page: number = 1
): Promise<{
  reviews: AppReview[];
  rating_breakdown: Record<string, number> | null;
}> {
  const reviewData = await store.reviews({
    id: Number(appId),
    country,
    page,
    sort: store.sort.RECENT,
  });

  const reviews: AppReview[] = reviewData.map((r: any) => ({
    external_id: String(r.id),
    author: r.userName || null,
    title: r.title || null,
    body: r.text || null,
    rating: r.score || 0,
    review_date: r.date ? new Date(r.date).toISOString().slice(0, 10) : null,
    app_version: r.version || null,
    country_code: country.toUpperCase(),
  }));

  return { reviews, rating_breakdown: null };
}

export async function fetchDeveloperApps(
  developerId: string
): Promise<DeveloperApp[]> {
  const apps = await store.developer({ devId: Number(developerId) });

  return apps.map((info: any) => ({
    external_id: String(info.id),
    name: info.title || "",
    icon_url: info.icon || null,
    rating: info.score || null,
    rating_count: info.reviews || null,
    price_model: info.free ? "free" : "paid",
    price: info.price ?? 0,
    currency: info.currency ?? null,
    category: info.primaryGenre || null,
    category_id: info.primaryGenreId != null ? String(info.primaryGenreId) : null,
  }));
}

const COLLECTION_MAP: Record<string, string> = {
  top_free: store.collection.TOP_FREE_IOS,
  top_paid: store.collection.TOP_PAID_IOS,
  top_grossing: store.collection.TOP_GROSSING_IOS,
};

export async function fetchChart(
  collection: string,
  category?: number,
  country: string = "us",
  num: number = 200
): Promise<ChartEntry[]> {
  const collectionValue = COLLECTION_MAP[collection];
  if (!collectionValue) {
    throw new Error(`Invalid collection: ${collection}. Use: ${Object.keys(COLLECTION_MAP).join(", ")}`);
  }

  const safeNum = Math.max(num, 2);
  const opts: Record<string, any> = { collection: collectionValue, num: safeNum, country };
  if (category) opts.category = category;

  let results;
  try {
    results = await store.list(opts);
  } catch (err: any) {
    console.warn(`[chart] empty or failed: ${collection} ${country} cat=${category ?? 'all'} — ${err.message}`);
    return [];
  }

  if (!results || !Array.isArray(results)) {
    return [];
  }

  return results.map((app: any, index: number) => ({
    rank: index + 1,
    app_id: String(app.id),
    name: app.title || "",
    icon_url: app.icon || null,
    developer: app.developer || null,
    developer_id: app.developerId ? String(app.developerId).split("?")[0] : null,
    genre: app.genre || null,
    genre_id: app.genreId ? String(app.genreId) : null,
    price: app.price ?? null,
    currency: app.currency ?? null,
    free: app.free ?? true,
    rating: null,
    version: app.version || null,
    released: app.released ? new Date(app.released).toISOString().slice(0, 10) : null,
  }));
}

export async function searchApps(
  term: string,
  num: number = 10,
  country: string = "us"
): Promise<SearchResult[]> {
  const results = await store.search({ term, num, country });

  return results.map((info: any) => ({
    app_id: String(info.id),
    name: info.title || "",
    developer: info.developer || "",
    developer_id: info.developerId ? String(info.developerId).split("?")[0] : null,
    icon_url: info.icon || null,
    rating: info.score || null,
    version: info.version || null,
    price: info.price || null,
    currency: info.currency ?? null,
    free: info.free ?? true,
  }));
}
