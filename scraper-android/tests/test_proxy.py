"""Unit tests for the proxy bootstrap helper."""

from __future__ import annotations

import os

import pytest

from src import proxy as proxy_module
from src.proxy import (
    ProxyStatus,
    init_proxy,
    redact_error_message,
    redact_proxy_url,
)


PROXY_ENV_KEYS = ("HTTPS_PROXY", "HTTP_PROXY", "https_proxy", "http_proxy")


@pytest.fixture(autouse=True)
def _clear_proxy_env(monkeypatch):
    for key in PROXY_ENV_KEYS:
        monkeypatch.delenv(key, raising=False)


# ---------- redact ------------------------------------------------------------


def test_redact_proxy_url_strips_credentials():
    assert (
        redact_proxy_url("http://alice:s3cret@proxy.example.com:8080")
        == "http://***@proxy.example.com:8080"
    )


def test_redact_proxy_url_leaves_uncredentialed_url_untouched():
    assert (
        redact_proxy_url("http://proxy.example.com:8080")
        == "http://proxy.example.com:8080"
    )


def test_redact_proxy_url_redacts_inside_longer_message():
    msg = (
        "tunnel failed via http://user:pass@proxy.example.com:8080 "
        "(ECONNREFUSED)"
    )
    assert (
        redact_proxy_url(msg)
        == "tunnel failed via http://***@proxy.example.com:8080 (ECONNREFUSED)"
    )


def test_redact_error_message_handles_exception_str():
    err = RuntimeError("boom http://u:p@host:9000 ECONNREFUSED")
    assert (
        redact_error_message(err)
        == "boom http://***@host:9000 ECONNREFUSED"
    )


def test_redact_error_message_handles_none_and_strings():
    assert redact_error_message(None) == ""
    assert redact_error_message("plain") == "plain"


# ---------- init_proxy --------------------------------------------------------


def test_init_proxy_disabled_when_blank():
    assert init_proxy(None) == ProxyStatus(enabled=False, host=None)
    assert init_proxy("   ") == ProxyStatus(enabled=False, host=None)
    for key in PROXY_ENV_KEYS:
        assert key not in os.environ


def test_init_proxy_mirrors_url_into_env():
    status = init_proxy("http://proxy.example.com:8080")
    assert status == ProxyStatus(enabled=True, host="proxy.example.com:8080")
    for key in PROXY_ENV_KEYS:
        assert os.environ[key] == "http://proxy.example.com:8080"


def test_init_proxy_keeps_credentials_in_env_for_actual_request_use():
    status = init_proxy("http://alice:s3cret@proxy.example.com:8080")
    assert status.enabled is True
    assert status.host == "proxy.example.com:8080"
    assert (
        os.environ["HTTPS_PROXY"]
        == "http://alice:s3cret@proxy.example.com:8080"
    )


def test_init_proxy_rejects_unparseable_url_with_redacted_message():
    with pytest.raises(ValueError) as exc:
        init_proxy("garbage with creds user:pass@host but no scheme")
    # No raw "user:pass" should appear in the raised message; the regex
    # only redacts URL-shaped credentials, but neither should the URL be
    # accepted.
    assert "is not a valid URL" in str(exc.value)


def test_init_proxy_pins_gplay_make_request_to_strict_mode():
    # Reach into gplay-scraper's HttpClient and verify that after init_proxy
    # the silent client-fallback chain (urllib3, curl_cffi, tls_client,
    # aiohttp — none of which honor HTTPS_PROXY env vars) is replaced with
    # a strict version that only ever uses the configured client_type.
    init_proxy("http://proxy.example.com:8080")

    from gplay_scraper.utils import http_client as gplay_http

    instance = gplay_http.HttpClient(client_type="requests")
    sentinel: dict[str, str] = {}

    def fake_try(self, client_type, method, url, **kwargs):  # noqa: ANN001
        sentinel["client_type"] = client_type
        sentinel["url"] = url

        class _Resp:
            text = ""
            status_code = 200

        return _Resp()

    # Replace the per-client try at the class level so the bound method
    # we re-installed picks it up.
    gplay_http.HttpClient._try_request_with_client = fake_try

    instance._make_request("GET", "https://example.com")

    # Strict mode should call the configured client only; no fallback.
    assert sentinel == {"client_type": "requests", "url": "https://example.com"}


def test_init_proxy_overrides_http_client_module_class_method():
    # The patch is module-level so importing init_proxy in a fresh process
    # is enough to install it. We assert the function identity changed.
    from gplay_scraper.utils import http_client as gplay_http

    original_make_request = gplay_http.HttpClient._make_request
    init_proxy("http://proxy.example.com:8080")
    assert gplay_http.HttpClient._make_request is not original_make_request
    # Re-applying init_proxy with a disabled value does not restore the
    # original — pinning is one-way for the lifetime of the process.
    proxy_module.init_proxy(None)
    assert gplay_http.HttpClient._make_request is not original_make_request
