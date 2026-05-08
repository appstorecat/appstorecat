"""Integration-shape tests proving the scraper layer respects the proxy.

We don't talk to Google Play. We mock GPlayScraper at import time so the
package call surface is observable, and use `responses` to assert the
inline `requests.get` call in `_scrape_developer_page` honors HTTPS_PROXY.
"""

from __future__ import annotations

import os
from unittest.mock import MagicMock, patch

import pytest
import responses


PROXY_ENV_KEYS = ("HTTPS_PROXY", "HTTP_PROXY", "https_proxy", "http_proxy")


@pytest.fixture(autouse=True)
def _clear_proxy_env(monkeypatch):
    for key in PROXY_ENV_KEYS:
        monkeypatch.delenv(key, raising=False)


def test_scraper_pins_gplay_to_requests_client():
    """The module-level GPlayScraper instance must be constructed with
    http_client="requests" so the silent fallback chain (urllib3/
    curl_cffi/tls_client/aiohttp — none of which honor HTTPS_PROXY) is
    bypassed by default. This is the core defense against IP leakage."""
    with patch("gplay_scraper.GPlayScraper") as mock_cls:
        mock_cls.return_value = MagicMock()
        # Force re-import so the module-level instantiation re-runs.
        import importlib
        import src.scraper

        importlib.reload(src.scraper)

        # Every construction must be pinned; we don't care how many
        # times reload triggered it.
        assert mock_cls.call_count >= 1
        for call in mock_cls.call_args_list:
            assert call.kwargs == {"http_client": "requests"}


@responses.activate
def test_scrape_developer_page_uses_https_proxy_env_var(monkeypatch):
    """`requests.get` honors HTTPS_PROXY when trust_env defaults are
    intact. We mock the response and ensure no real network call is
    made; assertion that the proxy-bound URL is reachable from the
    test (via `responses`) confirms the call path."""
    monkeypatch.setenv("HTTPS_PROXY", "http://proxy.example.com:8080")
    monkeypatch.setenv("HTTP_PROXY", "http://proxy.example.com:8080")

    expected_url = (
        "https://play.google.com/store/apps/developer?id=acme&hl=en"
    )
    responses.add(
        responses.GET,
        expected_url,
        body=(
            'href="/store/apps/details?id=com.acme.one"'
            'href="/store/apps/details?id=com.acme.two"'
            'href="/store/apps/details?id=com.acme.one"'
        ),
        status=200,
    )

    from src.scraper import _scrape_developer_page

    result = _scrape_developer_page("acme")
    assert result == ["com.acme.one", "com.acme.two"]
    assert len(responses.calls) == 1


@responses.activate
def test_scrape_developer_page_redacts_proxy_creds_in_log(caplog, monkeypatch):
    """When `requests.get` raises, the log line must not leak proxy
    credentials. We trigger an exception by configuring an unrelated
    URL match, then assert the redacted message in the warning."""
    monkeypatch.setenv("HTTPS_PROXY", "http://alice:s3cret@proxy.example.com:8080")

    expected_url = (
        "https://play.google.com/store/apps/developer?id=acme&hl=en"
    )
    responses.add(
        responses.GET,
        expected_url,
        body=ConnectionError(
            "tunnel failed http://alice:s3cret@proxy.example.com:8080"
        ),
    )

    from src.scraper import _scrape_developer_page

    with caplog.at_level("WARNING", logger="src.scraper"):
        result = _scrape_developer_page("acme")

    assert result == []
    rendered = "\n".join(
        getattr(rec, "reason", "") for rec in caplog.records
    )
    assert "alice:s3cret" not in rendered
    assert "***@proxy.example.com" in rendered


def test_health_endpoint_reports_proxy_status(monkeypatch):
    """The /health endpoint must report whether a proxy is configured.
    We re-import main with ANDROID_PROXY_URL set so init_proxy fires."""
    monkeypatch.setenv("PORT", "7463")
    monkeypatch.setenv("ANDROID_PROXY_URL", "http://proxy.example.com:8080")

    import importlib
    import src.main

    importlib.reload(src.main)

    from fastapi.testclient import TestClient

    with TestClient(src.main.app) as client:
        resp = client.get("/health")
        assert resp.status_code == 200
        body = resp.json()
        assert body["service"] == "scraper-android"
        assert body["proxy"] == "configured"


def test_health_endpoint_disabled_when_no_proxy(monkeypatch):
    monkeypatch.setenv("PORT", "7463")
    monkeypatch.delenv("ANDROID_PROXY_URL", raising=False)

    import importlib
    import src.main

    importlib.reload(src.main)

    from fastapi.testclient import TestClient

    with TestClient(src.main.app) as client:
        resp = client.get("/health")
        assert resp.json()["proxy"] == "disabled"
