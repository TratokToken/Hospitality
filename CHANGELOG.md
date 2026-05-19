# Changelog

All notable changes to the Tratok Hospitality API are documented here.

The format is loosely based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the API follows additive evolution: backwards-incompatible changes are
flagged 30 days ahead via email and a `[Breaking]` entry below.

---

## [Unreleased]

Nothing yet.

---

## [2026.05] — 2026-05-19

### Added
- This documentation repository, OpenAPI 3.1 spec, and example clients
  (Python, Node.js, PHP, curl).
- Webhook subscription model with HMAC-SHA256 signing
  (`X-Webhook-Signature` header).
- `pricing_bulk` and `inventory_bulk` endpoints for range writes.

### Changed
- API keys are accepted **only** via the `X-API-Key` header. Query-string
  keys (`?api_key=…`) were removed because they leak into access logs,
  browser history, and `Referer` headers. Old query-string callers must
  migrate to the header.

### Security
- All webhook deliveries carry an HMAC-SHA256 signature of the JSON body,
  derived from the per-webhook secret. Verify before trusting any payload.
- Webhook URLs are SSRF-screened — private/loopback hosts are rejected on
  registration.

---

## [2026.04] — 2026-04 (initial public release)

### Added
- `GET /api.php?endpoint=me`
- `GET /api.php?endpoint=properties[&id=…]`
- `GET /api.php?endpoint=rooms[&property_id=…|&id=…]`
- `GET|POST /api.php?endpoint=pricing`
- `GET|POST /api.php?endpoint=inventory`
- `GET /api.php?endpoint=activities[&id=…]`
- `GET /api.php?endpoint=restaurants[&id=…]`
- `GET /api.php?endpoint=bookings[&type=…&status=…&id=…]`
- `GET /api.php?endpoint=revenue`
- `POST /api.php?endpoint=api_key` — rotates the calling key
- Default per-key rate limit of 100 requests/minute, configurable per
  provider on request to `api-support@tratok.net`.
- iCal channel-sync export tokens (separate `ical_export.php` endpoint).
