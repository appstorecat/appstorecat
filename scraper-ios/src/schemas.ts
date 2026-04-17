/**
 * Shared OpenAPI schemas for App Store API.
 * These mirror gplay-api schemas for unified data model.
 */

export const ScreenshotSchema = {
  type: "object" as const,
  properties: {
    url: { type: "string" as const },
    device_type: { type: "string" as const },
    order: { type: "integer" as const },
  },
  required: ["url", "device_type", "order"],
};

export const AppIdentitySchema = {
  type: "object" as const,
  properties: {
    app_id: { type: "string" as const, description: "App Store track ID" },
    name: { type: "string" as const },
    publisher_name: { type: "string" as const },
    publisher_external_id: { type: "string" as const, nullable: true },
    publisher_url: { type: "string" as const, nullable: true },
    category: { type: "string" as const },
    category_id: { type: "string" as const, nullable: true },
    content_rating: { type: "string" as const, nullable: true },
    supported_locales: {
      type: "array" as const,
      items: { type: "string" as const },
      nullable: true,
    },
    original_release_date: { type: "string" as const, nullable: true },
    price_model: { type: "string" as const },
    price: { type: "number" as const, nullable: true },
    currency: { type: "string" as const, nullable: true },
    store_url: { type: "string" as const, nullable: true },
    version: { type: "string" as const, nullable: true },
    current_version_release_date: { type: "string" as const, nullable: true },
  },
  required: ["app_id", "name"],
};

export const StoreListingSchema = {
  type: "object" as const,
  properties: {
    platform: { type: "string" as const },
    locale: { type: "string" as const },
    title: { type: "string" as const },
    subtitle: { type: "string" as const, nullable: true },
    short_description: { type: "string" as const, nullable: true },
    description: { type: "string" as const },
    promotional_text: { type: "string" as const, nullable: true },
    whats_new: { type: "string" as const, nullable: true },
    icon_url: { type: "string" as const, nullable: true },
    screenshots: { type: "array" as const, items: ScreenshotSchema },
    video_url: { type: "string" as const, nullable: true },
    description_length: { type: "integer" as const },
    price: { type: "number" as const, nullable: true },
    currency: { type: "string" as const, nullable: true },
  },
  required: ["platform", "locale", "title", "description"],
};

export const AppMetricsSchema = {
  type: "object" as const,
  properties: {
    rating: { type: "number" as const },
    rating_count: { type: "integer" as const },
    rating_breakdown: {
      type: "object" as const,
      nullable: true,
      additionalProperties: { type: "integer" as const },
    },
    installs_range: { type: "string" as const, nullable: true },
    file_size_bytes: { type: "integer" as const, nullable: true },
  },
};

export const AppReviewSchema = {
  type: "object" as const,
  properties: {
    external_id: { type: "string" as const },
    author: { type: "string" as const, nullable: true },
    title: { type: "string" as const, nullable: true },
    body: { type: "string" as const, nullable: true },
    rating: { type: "integer" as const },
    review_date: { type: "string" as const, nullable: true },
    app_version: { type: "string" as const, nullable: true },
    country_code: { type: "string" as const },
  },
  required: ["external_id"],
};

export const ReviewsResponseSchema = {
  type: "object" as const,
  properties: {
    reviews: { type: "array" as const, items: AppReviewSchema },
    rating_breakdown: {
      type: "object" as const,
      nullable: true,
      additionalProperties: { type: "integer" as const },
    },
  },
};

export const DeveloperAppSchema = {
  type: "object" as const,
  properties: {
    external_id: { type: "string" as const },
    name: { type: "string" as const },
    icon_url: { type: "string" as const, nullable: true },
    rating: { type: "number" as const, nullable: true },
    rating_count: { type: "integer" as const, nullable: true },
    price_model: { type: "string" as const },
    category: { type: "string" as const, nullable: true },
    category_id: { type: "string" as const, nullable: true },
  },
  required: ["external_id", "name"],
};

export const DeveloperAppsResponseSchema = {
  type: "object" as const,
  properties: {
    apps: { type: "array" as const, items: DeveloperAppSchema },
  },
};

export const SearchResultSchema = {
  type: "object" as const,
  properties: {
    app_id: { type: "string" as const },
    name: { type: "string" as const },
    developer: { type: "string" as const },
    developer_id: { type: "string" as const, nullable: true },
    icon_url: { type: "string" as const, nullable: true },
    rating: { type: "number" as const, nullable: true },
    version: { type: "string" as const, nullable: true },
    genre: { type: "string" as const, nullable: true },
    genre_id: { type: "string" as const, nullable: true },
    price: { type: "number" as const, nullable: true },
    free: { type: "boolean" as const },
    currency: { type: "string" as const, nullable: true },
  },
  required: ["app_id", "name"],
};

export const SearchResponseSchema = {
  type: "object" as const,
  properties: {
    results: { type: "array" as const, items: SearchResultSchema },
  },
};

export const LocalizedListingsResponseSchema = {
  type: "object" as const,
  properties: {
    listings: { type: "array" as const, items: StoreListingSchema },
  },
};

export const ChartEntrySchema = {
  type: "object" as const,
  properties: {
    rank: { type: "integer" as const },
    app_id: { type: "string" as const },
    name: { type: "string" as const },
    icon_url: { type: "string" as const, nullable: true },
    developer: { type: "string" as const, nullable: true },
    developer_id: { type: "string" as const, nullable: true },
    genre: { type: "string" as const, nullable: true },
    genre_id: { type: "string" as const, nullable: true },
    price: { type: "number" as const, nullable: true },
    free: { type: "boolean" as const },
    currency: { type: "string" as const, nullable: true },
    rating: { type: "number" as const, nullable: true },
    version: { type: "string" as const, nullable: true },
    released: { type: "string" as const, nullable: true },
  },
  required: ["rank", "app_id", "name"],
};

export const ChartResponseSchema = {
  type: "object" as const,
  properties: {
    results: { type: "array" as const, items: ChartEntrySchema },
  },
};

export const HealthResponseSchema = {
  type: "object" as const,
  properties: {
    status: { type: "string" as const },
    service: { type: "string" as const },
  },
};

export const ErrorResponseSchema = {
  type: "object" as const,
  properties: {
    error: { type: "string" as const },
    detail: { type: "string" as const, nullable: true },
  },
};

// TypeScript interfaces
export interface Screenshot {
  url: string;
  device_type: string;
  order: number;
}

export interface AppIdentity {
  app_id: string;
  name: string;
  publisher_name: string;
  publisher_external_id: string | null;
  publisher_url: string | null;
  category: string;
  category_id: string | null;
  content_rating: string | null;
  supported_locales: string[] | null;
  original_release_date: string | null;
  price_model: string;
  price: number | null;
  currency: string | null;
  store_url: string | null;
  version: string | null;
  current_version_release_date: string | null;
}

export interface StoreListing {
  platform: string;
  locale: string;
  title: string;
  subtitle: string | null;
  short_description: string | null;
  description: string;
  promotional_text: string | null;
  whats_new: string | null;
  icon_url: string | null;
  screenshots: Screenshot[];
  video_url: string | null;
  description_length: number;
  price: number | null;
  currency: string | null;
}

export interface AppMetrics {
  rating: number;
  rating_count: number;
  rating_breakdown: Record<string, number> | null;
  installs_range: string | null;
  file_size_bytes: number | null;
}

export interface AppReview {
  external_id: string;
  author: string | null;
  title: string | null;
  body: string | null;
  rating: number;
  review_date: string | null;
  app_version: string | null;
  country_code: string;
}

export interface DeveloperApp {
  external_id: string;
  name: string;
  icon_url: string | null;
  rating: number | null;
  rating_count: number | null;
  price_model: string;
  category: string | null;
  category_id: string | null;
}

export interface ChartEntry {
  rank: number;
  app_id: string;
  name: string;
  icon_url: string | null;
  developer: string | null;
  developer_id: string | null;
  genre: string | null;
  genre_id: string | null;
  price: number | null;
  free: boolean;
  currency: string | null;
  rating: number | null;
  version: string | null;
  released: string | null;
}

export interface SearchResult {
  app_id: string;
  name: string;
  developer: string;
  developer_id: string | null;
  icon_url: string | null;
  rating: number | null;
  version: string | null;
  genre: string | null;
  genre_id: string | null;
  price: number | null;
  free: boolean;
  currency: string | null;
}
