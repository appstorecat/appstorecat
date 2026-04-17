"""Shared Swagger/OpenAPI schemas for Google Play API."""

from pydantic import BaseModel, Field


class Screenshot(BaseModel):
    url: str
    device_type: str = "phone"
    order: int = 0


class AppIdentity(BaseModel):
    app_id: str = Field(..., description="Google Play package name")
    name: str
    publisher_name: str = ""
    publisher_external_id: str | None = None
    publisher_url: str | None = None
    category: str = ""
    category_id: str | None = None
    content_rating: str | None = None
    supported_locales: list[str] | None = None
    original_release_date: str | None = None
    price_model: str = "free"
    price: float = 0
    currency: str | None = None
    store_url: str | None = None
    version: str | None = None
    current_version_release_date: str | None = None


class StoreListing(BaseModel):
    platform: str = "android"
    locale: str
    title: str
    subtitle: str | None = None
    short_description: str | None = None
    description: str = ""
    whats_new: str | None = None
    icon_url: str | None = None
    screenshots: list[Screenshot] = []
    video_url: str | None = None
    description_length: int = 0
    price: float = 0
    currency: str | None = None


class AppMetrics(BaseModel):
    rating: float = 0
    rating_count: int = 0
    rating_breakdown: dict[str, int] | None = None
    installs_range: str | None = None
    file_size_bytes: int = 0


class AppReview(BaseModel):
    external_id: str
    author: str | None = None
    title: str | None = None
    body: str | None = None
    rating: int = 0
    review_date: str | None = None
    app_version: str | None = None
    country_code: str = "US"


class ReviewsResponse(BaseModel):
    reviews: list[AppReview] = []
    rating_breakdown: dict[str, int] | None = None


class DeveloperApp(BaseModel):
    external_id: str
    name: str
    icon_url: str | None = None
    rating: float | None = None
    rating_count: int | None = None
    price_model: str = "free"
    price: float = 0
    currency: str | None = None
    category: str | None = None
    category_id: str | None = None


class DeveloperAppsResponse(BaseModel):
    apps: list[DeveloperApp] = []


class SearchResult(BaseModel):
    app_id: str
    name: str
    developer: str = ""
    developer_id: str | None = None
    icon_url: str | None = None
    rating: float | None = None
    version: str | None = None
    price: float | None = None
    free: bool = True
    currency: str | None = None


class SearchResponse(BaseModel):
    results: list[SearchResult] = []


class LocalizedListingsResponse(BaseModel):
    listings: list[StoreListing] = []


class ChartEntry(BaseModel):
    rank: int
    app_id: str
    name: str
    icon_url: str | None = None
    developer: str | None = None
    developer_id: str | None = None
    genre: str | None = None
    genre_id: str | None = None
    price: float | None = None
    free: bool = True
    rating: float | None = None
    version: str | None = None
    currency: str | None = None


class ChartResponse(BaseModel):
    results: list[ChartEntry] = []


class HealthResponse(BaseModel):
    status: str = "ok"
    service: str = "scraper-android"


class ErrorResponse(BaseModel):
    error: str
    detail: str | None = None
