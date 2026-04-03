"""Google Play API — FastAPI application."""

import os

from fastapi import FastAPI, HTTPException, Query
from fastapi.responses import RedirectResponse

from .schemas import (
    AppIdentity,
    AppMetrics,
    ChartResponse,
    DeveloperAppsResponse,
    ErrorResponse,
    HealthResponse,
    LocalizedListingsResponse,
    ReviewsResponse,
    SearchResponse,
    StoreListing,
)
from . import scraper

PORT = int(os.environ["PORT"]) if os.environ.get("PORT") else None
PUBLIC_URL = os.environ.get("PUBLIC_URL", f"http://localhost:{PORT}" if PORT else "")

app = FastAPI(
    title="Google Play Scraper API",
    description="Scraper API for Google Play Store app data",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
    servers=[{"url": PUBLIC_URL}],
)


@app.get("/", include_in_schema=False)
def index():
    html = """<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>AppStoreCat Scraper - Google Play</title>
<style>body{margin:0;background:#000;display:flex;justify-content:center;align-items:center;height:100vh;font-family:system-ui,sans-serif}
a{color:#fff;font-size:1.25rem;text-decoration:none;opacity:.6;transition:opacity .2s}a:hover{opacity:1}</style></head>
<body><a href="https://github.com/ismailcaakir/appstorecat" target="_blank">github.com/ismailcaakir/appstorecat</a></body></html>"""
    from fastapi.responses import HTMLResponse
    return HTMLResponse(content=html)


@app.get("/health", response_model=HealthResponse, tags=["system"])
def health():
    return HealthResponse()


@app.get("/docs", include_in_schema=False)
def docs_redirect():
    return RedirectResponse(url="/docs")


@app.get(
    "/charts",
    response_model=ChartResponse,
    responses={500: {"model": ErrorResponse}},
    tags=["charts"],
    summary="Get top chart rankings",
)
def charts(
    collection: str = Query("top_free", description="top_free, top_paid, or top_grossing"),
    category: str = Query("APPLICATION", description="Category ID (e.g. APPLICATION, GAME_ACTION)"),
    country: str = Query("us", description="Country code"),
    count: int = Query(100, ge=1, le=200, description="Number of results"),
):
    try:
        return scraper.fetch_chart(collection, category, country, count)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get(
    "/apps/search",
    response_model=SearchResponse,
    responses={500: {"model": ErrorResponse}},
    tags=["apps"],
    summary="Search apps on Google Play",
)
def search_apps(
    term: str = Query(..., min_length=1, description="Search query"),
    limit: int = Query(10, ge=1, le=50, description="Max results"),
    country: str = Query("us", min_length=2, max_length=2, description="Country code"),
):
    try:
        return scraper.search_apps(term, limit, country=country)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get(
    "/apps/{app_id:path}/identity",
    response_model=AppIdentity,
    responses={404: {"model": ErrorResponse}, 500: {"model": ErrorResponse}},
    tags=["apps"],
    summary="Get app identity and details",
)
def get_app_identity(app_id: str, country: str = Query("us", min_length=2, max_length=2, description="Country code")):
    try:
        return scraper.fetch_identity(app_id, country=country)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get(
    "/apps/{app_id:path}/listings",
    response_model=StoreListing,
    responses={500: {"model": ErrorResponse}},
    tags=["apps"],
    summary="Get store listing for a locale",
)
def get_app_listing(
    app_id: str,
    locale: str = Query("en", description="Language code (e.g. en, tr, de)"),
    country: str = Query("us", description="Country code (e.g. us, tr, de)"),
):
    try:
        return scraper.fetch_listing(app_id, locale, country)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get(
    "/apps/{app_id:path}/listings/locales",
    response_model=LocalizedListingsResponse,
    responses={500: {"model": ErrorResponse}},
    tags=["apps"],
    summary="Get store listings for multiple locales",
)
def get_app_localized_listings(
    app_id: str,
    locales: str = Query(..., description="Comma-separated locale codes (e.g. en,tr,de)"),
):
    try:
        locale_list = [loc.strip() for loc in locales.split(",") if loc.strip()]
        return scraper.fetch_localized_listings(app_id, locale_list)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get(
    "/apps/{app_id:path}/metrics",
    response_model=AppMetrics,
    responses={500: {"model": ErrorResponse}},
    tags=["apps"],
    summary="Get app metrics (rating, installs, etc.)",
)
def get_app_metrics(app_id: str, country: str = Query("us", min_length=2, max_length=2, description="Country code")):
    try:
        return scraper.fetch_metrics(app_id, country=country)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get(
    "/apps/{app_id:path}/reviews",
    response_model=ReviewsResponse,
    responses={500: {"model": ErrorResponse}},
    tags=["apps"],
    summary="Get app reviews",
)
def get_app_reviews(
    app_id: str,
    country: str = Query("us", description="Country code"),
    limit: int = Query(200, ge=1, le=500, description="Max reviews"),
):
    try:
        return scraper.fetch_reviews(app_id, country, limit)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get(
    "/developers/{developer_id}/apps",
    response_model=DeveloperAppsResponse,
    responses={500: {"model": ErrorResponse}},
    tags=["developers"],
    summary="Get all apps by a developer",
)
def get_developer_apps(developer_id: str):
    try:
        return scraper.fetch_developer_apps(developer_id)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get(
    "/developers/search",
    response_model=SearchResponse,
    responses={500: {"model": ErrorResponse}},
    tags=["developers"],
    summary="Search for developers (searches apps, returns unique developers)",
)
def search_developers(
    term: str = Query(..., min_length=1, description="Search query"),
    limit: int = Query(10, ge=1, le=50, description="Max results"),
    country: str = Query("us", min_length=2, max_length=2, description="Country code"),
):
    try:
        return scraper.search_apps(term, limit, country=country)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
