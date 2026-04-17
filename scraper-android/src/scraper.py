"""Google Play scraper wrapper using gplay-scraper package."""

import re
import logging
from datetime import date

import requests
from gplay_scraper import GPlayScraper

from .schemas import (
    AppIdentity,
    AppMetrics,
    AppReview,
    ChartEntry,
    ChartResponse,
    DeveloperApp,
    DeveloperAppsResponse,
    LocalizedListingsResponse,
    ReviewsResponse,
    Screenshot,
    SearchResponse,
    SearchResult,
    StoreListing,
)

ALL_APP_FIELDS = [
    "appId", "title", "summary", "description", "genre", "contentRating",
    "released", "lastUpdated", "version", "icon", "headerImage",
    "screenshots", "video", "installs", "realInstalls", "minInstalls",
    "score", "ratings", "histogram", "price", "free", "currency",
    "developer", "developerId", "developerEmail", "developerWebsite",
    "permissions", "dataSafety", "appUrl", "whatsNew",
]

REVIEW_FIELDS = ["reviewId", "userName", "content", "score", "at", "reviewCreatedVersion"]

SEARCH_FIELDS = ["appId", "title", "developer", "developerId", "icon", "score", "free", "price", "currency", "version"]

CHART_FIELDS = ["appId", "title", "icon", "developer", "developerId", "genre", "genreId", "price", "free", "score", "currency", "version"]

COLLECTION_MAP = {
    "top_free": "topselling_free",
    "top_paid": "topselling_paid",
    "top_grossing": "topgrossing",
}

DEVELOPER_FIELDS = ["appId", "title", "icon", "score", "ratings", "free", "genre", "genreId"]

scraper = GPlayScraper()


def _resolve_developer_id(info: dict) -> str | None:
    """Google Play developer page uses URL-encoded name, not numeric ID."""
    name = info.get("developer", "")
    if name:
        return name.replace(" ", "+")
    return info.get("developerId")


def fetch_identity(app_id: str, country: str = "us") -> AppIdentity:
    """Fetch app identity/details from Google Play."""
    info = scraper.app_get_fields(app_id, [
        "appId", "title", "developer", "developerId", "developerWebsite",
        "genre", "genreId", "contentRating", "released", "lastUpdated", "free",
        "appUrl", "version", "price", "currency",
    ], country=country)

    version = info.get("version")
    if not version or version == "Varies with device":
        version = f"ag.{date.today().strftime('%Y%m%d')}"

    return AppIdentity(
        app_id=info.get("appId", app_id),
        name=info.get("title", ""),
        publisher_name=info.get("developer", ""),
        publisher_external_id=_resolve_developer_id(info),
        publisher_url=info.get("developerWebsite"),
        category=info.get("genre", ""),
        category_id=info.get("genreId"),
        content_rating=info.get("contentRating"),
        supported_locales=None,
        original_release_date=info.get("released"),
        price_model="free" if info.get("free", True) else "paid",
        price=info.get("price", 0) or 0,
        currency=info.get("currency"),
        store_url=info.get("appUrl"),
        version=version,
        current_version_release_date=info.get("lastUpdated") or date.today().isoformat(),
    )


def fetch_listing(app_id: str, locale: str = "en", country: str = "us") -> StoreListing:
    """Fetch store listing for a specific locale."""
    info = scraper.app_get_fields(app_id, [
        "title", "summary", "description", "whatsNew", "icon",
        "screenshots", "video", "price", "free", "currency",
    ], lang=locale, country=country)

    screenshots = []
    for i, url in enumerate(info.get("screenshots", []) or []):
        screenshots.append(Screenshot(url=url, device_type="phone", order=i))

    description = info.get("description", "") or ""

    whats_new = info.get("whatsNew")
    if isinstance(whats_new, list):
        whats_new = "\n".join(whats_new)
    if not whats_new:
        whats_new = "Bug fixes and performance improvements."

    return StoreListing(
        platform="android",
        locale=locale,
        title=info.get("title", ""),
        subtitle=info.get("summary"),
        short_description=info.get("summary"),
        description=description,
        whats_new=whats_new,
        icon_url=info.get("icon"),
        screenshots=screenshots,
        video_url=info.get("video"),
        price=info.get("price", 0) or 0,
        currency=info.get("currency"),
        description_length=len(description),
    )


def fetch_localized_listings(
    app_id: str, locales: list[str]
) -> LocalizedListingsResponse:
    """Fetch store listings for multiple locales."""
    listings = []

    for locale in locales:
        try:
            listing = fetch_listing(app_id, locale=locale)
            listings.append(listing)
        except Exception:
            continue

    return LocalizedListingsResponse(listings=listings)


def fetch_metrics(app_id: str, country: str = "us") -> AppMetrics:
    """Fetch app metrics (rating, installs, etc.)."""
    info = scraper.app_get_fields(app_id, [
        "score", "ratings", "histogram", "realInstalls", "minInstalls",
    ], country=country)

    installs = info.get("realInstalls") or info.get("minInstalls")
    installs_range = f"{installs:,}+" if installs else None

    histogram = info.get("histogram")
    rating_breakdown = None
    if histogram and len(histogram) == 5:
        rating_breakdown = {
            "5": histogram[4],
            "4": histogram[3],
            "3": histogram[2],
            "2": histogram[1],
            "1": histogram[0],
        }

    return AppMetrics(
        rating=info.get("score", 0) or 0,
        rating_count=info.get("ratings", 0) or 0,
        rating_breakdown=rating_breakdown,
        installs_range=installs_range,
    )


def fetch_reviews(
    app_id: str, country: str = "us", count: int = 200
) -> ReviewsResponse:
    """Fetch app reviews."""
    review_list = scraper.reviews_get_fields(app_id, REVIEW_FIELDS)

    mapped = []
    for r in (review_list or [])[:count]:
        review_date = r.get("at", "")
        if review_date and "T" in str(review_date):
            review_date = str(review_date)[:10]

        mapped.append(
            AppReview(
                external_id=r.get("reviewId", ""),
                author=r.get("userName"),
                title=None,
                body=r.get("content"),
                rating=r.get("score", 0),
                review_date=review_date,
                app_version=r.get("reviewCreatedVersion"),
                country_code=country.upper(),
            )
        )

    return ReviewsResponse(reviews=mapped)


def _scrape_developer_page(developer_id: str) -> list[str]:
    """Scrape Google Play developer page to get app IDs."""
    url = f"https://play.google.com/store/apps/developer?id={developer_id}&hl=en"
    headers = {"User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)"}

    try:
        resp = requests.get(url, headers=headers, timeout=15)
        if resp.status_code != 200:
            return []
        app_ids = re.findall(r"details\?id=([a-zA-Z0-9._]+)", resp.text)
        return list(dict.fromkeys(app_ids))
    except Exception as e:
        logging.warning(f"Developer page scrape failed: {e}")
        return []


def fetch_developer_apps(developer_id: str) -> DeveloperAppsResponse:
    """Fetch all apps by a developer via developer page scraping + enrichment."""
    app_ids = _scrape_developer_page(developer_id)

    apps = []
    for app_id in app_ids:
        try:
            info = scraper.app_get_fields(app_id, DEVELOPER_FIELDS + ["free"])
            if not info:
                continue
            apps.append(
                DeveloperApp(
                    external_id=info.get("appId", app_id),
                    name=info.get("title", ""),
                    icon_url=info.get("icon"),
                    rating=info.get("score"),
                    rating_count=info.get("ratings"),
                    price_model="free" if info.get("free", True) else "paid",
        price=info.get("price", 0) or 0,
        currency=info.get("currency"),
                    category=info.get("genre"),
                    category_id=info.get("genreId"),
                )
            )
        except Exception:
            continue

    return DeveloperAppsResponse(apps=apps)


def fetch_chart(
    collection: str = "top_free",
    category: str = "APPLICATION",
    country: str = "us",
    count: int = 100,
) -> ChartResponse:
    """Fetch top chart rankings from Google Play."""
    collection_value = COLLECTION_MAP.get(collection)
    if not collection_value:
        raise ValueError(f"Invalid collection: {collection}. Use: {', '.join(COLLECTION_MAP.keys())}")

    results = scraper.list_get_fields(
        collection=collection_value,
        fields=CHART_FIELDS,
        category=category,
        count=count,
        lang="en",
        country=country,
    )

    entries = []
    for i, info in enumerate(results or []):
        entries.append(
            ChartEntry(
                rank=i + 1,
                app_id=info.get("appId", ""),
                name=info.get("title", ""),
                icon_url=info.get("icon"),
                developer=info.get("developer"),
                developer_id=_resolve_developer_id(info),
                genre=info.get("genre"),
                genre_id=info.get("genreId"),
                price=info.get("price", 0),
                free=info.get("free", True),
                rating=info.get("score"),
                version=info.get("version"),
                currency=info.get("currency"),
            )
        )

    return ChartResponse(results=entries)


def search_apps(term: str, limit: int = 10, country: str = "us") -> SearchResponse:
    """Search for apps on Google Play."""
    results = scraper.search_get_fields(term, SEARCH_FIELDS, country=country)

    mapped = []
    for info in (results or [])[:limit]:
        mapped.append(
            SearchResult(
                app_id=info.get("appId", ""),
                name=info.get("title", ""),
                developer=info.get("developer", ""),
                developer_id=_resolve_developer_id(info),
                icon_url=info.get("icon"),
                rating=info.get("score"),
                version=info.get("version"),
                price=info.get("price", 0),
                free=info.get("free", True),
                currency=info.get("currency"),
            )
        )

    return SearchResponse(results=mapped)
