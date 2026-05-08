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


_SOCKS_SCHEMES = frozenset({"socks5", "socks5h", "socks"})
_HTTP_SCHEMES = frozenset({"http", "https"})
_PROXY_ENV_KEYS = ("HTTPS_PROXY", "HTTP_PROXY", "https_proxy", "http_proxy")


@dataclass(frozen=True)
class ProxyStatus:
    enabled: bool
    host: str | None
    scheme: str | None = None


def redact_proxy_url(value: str) -> str:
    return _PROXY_CRED_RE.sub("//***@", value)


def redact_error_message(value: object) -> str:
    return redact_proxy_url(str(value) if value is not None else "")


def _parse(url: str) -> tuple[str, str]:
    """Return (host[:port], normalized_scheme). Raises on unsupported scheme."""
    parsed = urlparse(url)
    if not parsed.scheme or not parsed.hostname:
        raise ValueError(
            f"ANDROID_PROXY_URL is not a valid URL: {redact_proxy_url(url)}"
        )
    scheme = parsed.scheme.lower()
    if scheme not in _SOCKS_SCHEMES and scheme not in _HTTP_SCHEMES:
        raise ValueError(
            "ANDROID_PROXY_URL must use http://, https://, or socks5://: "
            f"{redact_proxy_url(url)}"
        )
    host = (
        f"{parsed.hostname}:{parsed.port}" if parsed.port else parsed.hostname
    )
    normalized = "socks5" if scheme in _SOCKS_SCHEMES else scheme
    return host, normalized


def _pin_gplay_client_to_requests() -> None:
    """Force gplay-scraper to use only the `requests` client.

    The package's HttpClient._make_request silently iterates through
    7 client libraries on failure. Several of them (urllib3, curl_cffi,
    tls_client, aiohttp) do not honor HTTPS_PROXY env vars by default,
    so a transient failure on the primary client would leak the real
    egress IP through a fallback that bypasses the proxy. We override
    _make_request to only ever call the configured client_type.

    The patch is name-coupled to gplay-scraper's internals (verified
    against 1.0.6). If a future version renames either method, fail
    loudly at boot rather than silently letting the fallback chain
    leak the real IP.
    """
    from gplay_scraper.utils import http_client as gplay_http

    if not hasattr(gplay_http, "HttpClient") or not hasattr(
        gplay_http.HttpClient, "_try_request_with_client"
    ):
        raise RuntimeError(
            "gplay-scraper internal API changed: HttpClient._try_request_with_client "
            "is missing. Refusing to enable proxy without the silent-fallback patch — "
            "the urllib3/curl_cffi/tls_client/aiohttp fallback clients ignore "
            "HTTPS_PROXY and would leak the real egress IP."
        )

    def _strict_make_request(self, method: str, url: str, **kwargs):
        return self._try_request_with_client(
            self.client_type, method, url, **kwargs
        )

    gplay_http.HttpClient._make_request = _strict_make_request


def _clear_proxy_env() -> None:
    for key in _PROXY_ENV_KEYS:
        os.environ.pop(key, None)


def init_proxy(raw_url: str | None) -> ProxyStatus:
    if not raw_url or not raw_url.strip():
        # Drop any env vars a previous call may have left behind so a
        # runtime re-init with an empty URL fully disables proxying.
        _clear_proxy_env()
        return ProxyStatus(enabled=False, host=None, scheme=None)

    url = raw_url.strip()
    host, scheme = _parse(url)

    os.environ["HTTPS_PROXY"] = url
    os.environ["HTTP_PROXY"] = url
    os.environ["https_proxy"] = url
    os.environ["http_proxy"] = url

    _pin_gplay_client_to_requests()

    return ProxyStatus(enabled=True, host=host, scheme=scheme)
