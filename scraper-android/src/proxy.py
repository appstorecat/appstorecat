"""Proxy bootstrap for outbound store traffic.

Reads ANDROID_PROXY_URL once at startup. When set:
  * mirrors it into HTTPS_PROXY / HTTP_PROXY env vars so the `requests`
    library used by gplay-scraper picks it up automatically;
  * disables the silent client-fallback chain inside gplay-scraper
    (urllib3 / curl_cffi / tls_client / aiohttp do NOT honor the env
    vars, so a fallback would leak the real egress IP);
  * pins the gplay-scraper HttpClient to the `requests` client.

Also exposes a `redact_proxy_url` helper for safe error logging.
"""

from __future__ import annotations

import os
import re
from dataclasses import dataclass
from urllib.parse import urlparse


_PROXY_CRED_RE = re.compile(r"//[^@/\s]+@")


@dataclass(frozen=True)
class ProxyStatus:
    enabled: bool
    host: str | None


def redact_proxy_url(value: str) -> str:
    return _PROXY_CRED_RE.sub("//***@", value)


def redact_error_message(value: object) -> str:
    return redact_proxy_url(str(value) if value is not None else "")


def _parse_host(url: str) -> str:
    parsed = urlparse(url)
    if not parsed.scheme or not parsed.hostname:
        raise ValueError(
            f"ANDROID_PROXY_URL is not a valid URL: {redact_proxy_url(url)}"
        )
    if parsed.port:
        return f"{parsed.hostname}:{parsed.port}"
    return parsed.hostname


def _pin_gplay_client_to_requests() -> None:
    """Force gplay-scraper to use only the `requests` client.

    The package's HttpClient._make_request silently iterates through
    7 client libraries on failure. Several of them (urllib3, curl_cffi,
    tls_client, aiohttp) do not honor HTTPS_PROXY env vars by default,
    so a transient failure on the primary client would leak the real
    egress IP through a fallback that bypasses the proxy. We override
    _make_request to only ever call the configured client_type.
    """
    from gplay_scraper.utils import http_client as gplay_http

    def _strict_make_request(self, method: str, url: str, **kwargs):
        return self._try_request_with_client(
            self.client_type, method, url, **kwargs
        )

    gplay_http.HttpClient._make_request = _strict_make_request


def init_proxy(raw_url: str | None) -> ProxyStatus:
    if not raw_url or not raw_url.strip():
        return ProxyStatus(enabled=False, host=None)

    url = raw_url.strip()
    host = _parse_host(url)

    os.environ["HTTPS_PROXY"] = url
    os.environ["HTTP_PROXY"] = url
    os.environ["https_proxy"] = url
    os.environ["http_proxy"] = url

    _pin_gplay_client_to_requests()

    return ProxyStatus(enabled=True, host=host)
