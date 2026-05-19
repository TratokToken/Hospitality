"""
Tratok Hospitality API — thin Python client.

Single-file SDK. Depends only on `requests` (and `python-dateutil` for the
optional date helpers).

Usage:

    from tratok_client import TratokClient
    client = TratokClient(api_key=os.environ["TRATOK_API_KEY"])

    me = client.me()

    for prop in client.iter_properties():
        print(prop["id"], prop["name"])

    client.set_price(room_id=101, date="2026-06-04", price=219.00)
    client.set_price_range(room_id=101, start="2026-06-01",
                           end="2026-06-30", price=199.00)
"""
from __future__ import annotations

import time
from typing import Any, Iterator
from urllib.parse import urlencode

import requests

DEFAULT_BASE_URL = "https://hospitality.tratok.net/api.php"


class TratokAPIError(Exception):
    """Raised for any non-2xx response from the API."""

    def __init__(self, status: int, error: str, body: dict | None = None):
        super().__init__(f"[{status}] {error}")
        self.status = status
        self.error = error
        self.body = body or {}


class TratokRateLimitError(TratokAPIError):
    """Raised on HTTP 429. Carries the server's Retry-After (seconds)."""

    def __init__(self, status: int, error: str, retry_after: int, body=None):
        super().__init__(status, error, body)
        self.retry_after = retry_after


class TratokClient:
    """
    Wraps the single-dispatcher REST API at ``hospitality.tratok.net/api.php``.

    The client is stateless apart from the key, the base URL, and a
    `requests.Session` for keep-alive. Instantiate one client per
    integration; share it across threads (requests sessions are
    thread-safe for the usual ops).
    """

    def __init__(
        self,
        api_key: str,
        *,
        base_url: str = DEFAULT_BASE_URL,
        timeout: float = 30.0,
        session: requests.Session | None = None,
        auto_retry_429: bool = True,
        max_retries: int = 3,
    ) -> None:
        if not api_key:
            raise ValueError("api_key is required")
        self.api_key = api_key
        self.base_url = base_url
        self.timeout = timeout
        self.auto_retry_429 = auto_retry_429
        self.max_retries = max_retries
        self.session = session or requests.Session()
        self.session.headers.update({
            "X-API-Key": api_key,
            "Accept": "application/json",
            "User-Agent": "tratok-python/2026.05",
        })

    # ─── Low-level dispatcher ────────────────────────────────────────
    def _call(
        self,
        endpoint: str,
        *,
        method: str = "GET",
        params: dict | None = None,
        json: dict | None = None,
    ) -> Any:
        url = self.base_url
        query = {"endpoint": endpoint, **(params or {})}
        attempt = 0
        while True:
            response = self.session.request(
                method,
                f"{url}?{urlencode(query)}",
                json=json,
                timeout=self.timeout,
            )
            if response.status_code == 429 and self.auto_retry_429 and attempt < self.max_retries:
                wait = int(response.headers.get("Retry-After", "60"))
                time.sleep(wait)
                attempt += 1
                continue

            try:
                body = response.json()
            except ValueError:
                body = {}

            if not response.ok or body.get("success") is not True:
                err = body.get("error", response.reason)
                if response.status_code == 429:
                    raise TratokRateLimitError(
                        response.status_code, err,
                        retry_after=int(response.headers.get("Retry-After", "60")),
                        body=body,
                    )
                raise TratokAPIError(response.status_code, err, body=body)
            return body

    # ─── Account ────────────────────────────────────────────────────
    def me(self) -> dict:
        return self._call("me")["data"]

    def rotate_api_key(self) -> dict:
        """Rotate the key in use. The current client is left with the OLD
        key in `self.api_key`; the returned dict contains the new one.
        Update your secret store and instantiate a fresh client."""
        return self._call("api_key", method="POST")["data"]

    # ─── Properties ─────────────────────────────────────────────────
    def list_properties(self, *, page: int = 1, limit: int = 20) -> dict:
        return self._call("properties", params={"page": page, "limit": limit})

    def iter_properties(self, *, limit: int = 100) -> Iterator[dict]:
        page = 1
        while True:
            r = self.list_properties(page=page, limit=limit)
            for item in r["data"]:
                yield item
            if page >= r["pagination"]["pages"]:
                return
            page += 1

    def get_property(self, property_id: int) -> dict:
        return self._call("properties", params={"id": property_id})["data"]

    # ─── Rooms ──────────────────────────────────────────────────────
    def list_rooms(self, property_id: int) -> list[dict]:
        return self._call("rooms", params={"property_id": property_id})["data"]

    def get_room(self, room_id: int) -> dict:
        return self._call("rooms", params={"id": room_id})["data"]

    # ─── Pricing ────────────────────────────────────────────────────
    def get_pricing(self, room_id: int, *, from_: str | None = None, to: str | None = None) -> list[dict]:
        params = {"room_id": room_id}
        if from_:
            params["from"] = from_
        if to:
            params["to"] = to
        return self._call("pricing", params=params)["data"]

    def set_price(self, *, room_id: int, date: str, price: float) -> dict:
        return self._call(
            "pricing",
            method="POST",
            json={"room_id": room_id, "date": date, "price": price},
        )["data"]

    def set_price_range(self, *, room_id: int, start: str, end: str, price: float) -> dict:
        return self._call(
            "pricing_bulk",
            method="POST",
            json={"room_id": room_id, "start_date": start, "end_date": end, "price": price},
        )["data"]

    # ─── Inventory ──────────────────────────────────────────────────
    def get_inventory(self, room_id: int, *, from_: str | None = None, to: str | None = None) -> list[dict]:
        params = {"room_id": room_id}
        if from_:
            params["from"] = from_
        if to:
            params["to"] = to
        return self._call("inventory", params=params)["data"]

    def set_inventory(self, *, room_id: int, date: str, available: int) -> dict:
        return self._call(
            "inventory",
            method="POST",
            json={"room_id": room_id, "date": date, "available": available},
        )["data"]

    def set_inventory_range(self, *, room_id: int, start: str, end: str, available: int) -> dict:
        return self._call(
            "inventory_bulk",
            method="POST",
            json={"room_id": room_id, "start_date": start, "end_date": end, "available": available},
        )["data"]

    # ─── Activities + Restaurants ───────────────────────────────────
    def list_activities(self, *, page: int = 1, limit: int = 20) -> dict:
        return self._call("activities", params={"page": page, "limit": limit})

    def get_activity(self, activity_id: int) -> dict:
        return self._call("activities", params={"id": activity_id})["data"]

    def list_restaurants(self, *, page: int = 1, limit: int = 20) -> dict:
        return self._call("restaurants", params={"page": page, "limit": limit})

    def get_restaurant(self, restaurant_id: int) -> dict:
        return self._call("restaurants", params={"id": restaurant_id})["data"]

    # ─── Bookings ───────────────────────────────────────────────────
    def list_bookings(
        self,
        *,
        type: str | None = None,
        status: str | None = None,
        page: int = 1,
        limit: int = 20,
    ) -> dict:
        params: dict[str, Any] = {"page": page, "limit": limit}
        if type:
            params["type"] = type
        if status:
            params["status"] = status
        return self._call("bookings", params=params)

    def iter_bookings(self, **filters) -> Iterator[dict]:
        page = 1
        limit = filters.pop("limit", 100)
        while True:
            r = self.list_bookings(page=page, limit=limit, **filters)
            for item in r["data"]:
                yield item
            if page >= r["pagination"]["pages"]:
                return
            page += 1

    def get_booking(self, booking_id: int) -> dict:
        return self._call("bookings", params={"id": booking_id})["data"]

    # ─── Revenue ────────────────────────────────────────────────────
    def revenue(
        self,
        *,
        from_: str | None = None,
        to: str | None = None,
        type: str | None = None,
    ) -> dict:
        params: dict[str, Any] = {}
        if from_:
            params["from"] = from_
        if to:
            params["to"] = to
        if type:
            params["type"] = type
        return self._call("revenue", params=params)["data"]


# ─── Webhook signature verification ─────────────────────────────────
def verify_webhook(secret: str, raw_body: bytes, signature: str) -> bool:
    """
    Verify an X-Webhook-Signature header against the raw POST body.

    Use the RAW body bytes — re-serializing the parsed JSON will not
    produce the same signature.
    """
    import hmac
    import hashlib
    expected = hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, signature)
