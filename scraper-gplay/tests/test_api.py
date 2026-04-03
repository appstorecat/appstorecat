"""Tests for Google Play API endpoints."""

from unittest.mock import patch, MagicMock
from fastapi.testclient import TestClient

from src.main import app

client = TestClient(app)


def test_health():
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert data["service"] == "scraper-gplay"


@patch("src.scraper.scraper")
def test_get_app_identity(mock_scraper):
    mock_scraper.app_get_fields.return_value = {
        "appId": "com.example.app",
        "title": "Example App",
        "developer": "Example Dev",
        "developerId": "dev123",
        "developerWebsite": "https://example.com",
        "genre": "Tools",
        "contentRating": "Everyone",
        "released": "2024-01-01",
        "free": True,
        "appUrl": "https://play.google.com/store/apps/details?id=com.example.app",
        "version": "1.0.0",
        "lastUpdated": "2024-06-01",
    }

    response = client.get("/apps/com.example.app/identity")
    assert response.status_code == 200
    data = response.json()
    assert data["app_id"] == "com.example.app"
    assert data["name"] == "Example App"
    assert data["publisher_name"] == "Example Dev"
    assert data["price_model"] == "free"


@patch("src.scraper.scraper")
def test_get_app_listing(mock_scraper):
    mock_scraper.app_get_fields.return_value = {
        "title": "Example App",
        "summary": "A great app",
        "description": "Full description here",
        "whatsNew": "Bug fixes",
        "icon": "https://example.com/icon.png",
        "screenshots": ["https://example.com/s1.png", "https://example.com/s2.png"],
        "video": None,
    }

    response = client.get("/apps/com.example.app/listings?locale=en&country=us")
    assert response.status_code == 200
    data = response.json()
    assert data["title"] == "Example App"
    assert data["platform"] == "android"
    assert len(data["screenshots"]) == 2
    assert data["description_length"] == len("Full description here")


@patch("src.scraper.scraper")
def test_get_app_metrics(mock_scraper):
    mock_scraper.app_get_fields.return_value = {
        "score": 4.5,
        "ratings": 10000,
        "realInstalls": 500000,
        "histogram": [100, 200, 500, 2000, 7200],
    }

    response = client.get("/apps/com.example.app/metrics")
    assert response.status_code == 200
    data = response.json()
    assert data["rating"] == 4.5
    assert data["rating_count"] == 10000
    assert data["installs_range"] == "500,000+"
    assert data["rating_breakdown"]["5"] == 7200
    assert data["rating_breakdown"]["1"] == 100


@patch("src.scraper.scraper")
def test_get_app_reviews(mock_scraper):
    mock_scraper.reviews_get_fields.return_value = [
        {
            "reviewId": "r1",
            "userName": "John",
            "content": "Great app!",
            "score": 5,
            "at": "2024-06-01T10:00:00",
            "reviewCreatedVersion": "1.0.0",
        },
        {
            "reviewId": "r2",
            "userName": "Jane",
            "content": "Needs work",
            "score": 2,
            "at": "2024-06-02T12:00:00",
            "reviewCreatedVersion": "1.0.0",
        },
    ]

    response = client.get("/apps/com.example.app/reviews?country=us&limit=10")
    assert response.status_code == 200
    data = response.json()
    assert len(data["reviews"]) == 2
    assert data["reviews"][0]["external_id"] == "r1"
    assert data["reviews"][0]["rating"] == 5
    assert data["reviews"][1]["author"] == "Jane"


@patch("src.scraper.scraper")
def test_get_developer_apps(mock_scraper):
    mock_scraper.developer_get_fields.return_value = [
        {
            "appId": "com.example.app1",
            "title": "App 1",
            "icon": "https://example.com/icon1.png",
            "score": 4.2,
            "ratings": 500,
            "free": True,
            "genre": "Tools",
        },
    ]

    response = client.get("/developers/dev123/apps")
    assert response.status_code == 200
    data = response.json()
    assert len(data["apps"]) == 1
    assert data["apps"][0]["external_id"] == "com.example.app1"


@patch("src.scraper.scraper")
def test_search_apps(mock_scraper):
    mock_scraper.search_get_fields.return_value = [
        {
            "appId": "com.example.app",
            "title": "Example App",
            "developer": "Example Dev",
            "icon": "https://example.com/icon.png",
            "score": 4.5,
            "price": 0,
            "free": True,
        },
    ]

    response = client.get("/apps/search?term=example&limit=5")
    assert response.status_code == 200
    data = response.json()
    assert len(data["results"]) == 1
    assert data["results"][0]["app_id"] == "com.example.app"


def test_search_apps_missing_term():
    response = client.get("/apps/search")
    assert response.status_code == 422
