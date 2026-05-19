<div align="center">
  <img src="https://hospitality.tratok.net/logo.webp" alt="Tratok Hospitality" height="80">

# Tratok Hospitality API

**The provider integration surface for the Tratok hospitality platform.**

REST API for hotels, activity operators and restaurants on [tratok.net](https://tratok.net) — sync your inventory and pricing, pull bookings, subscribe to event webhooks, and run channel-manager calendars from your existing PMS or back-office.

[![License: MIT](https://img.shields.io/badge/License-MIT-E85D3A.svg)](LICENSE)
[![API Version](https://img.shields.io/badge/API-2026.05-2ECDA7.svg)](openapi.yaml)
[![Languages](https://img.shields.io/badge/Samples-PHP%20·%20Node%20·%20Python%20·%20cURL-F5A623.svg)](examples/)
[![Production](https://img.shields.io/badge/Production-Live-2ECDA7.svg)](https://hospitality.tratok.net)

[Documentation](#documentation) · [Quick Start](#quick-start) · [Examples](examples/) · [OpenAPI](openapi.yaml) · [Postman](postman_collection.json) · [Provider Portal](https://hospitality.tratok.net)

</div>

---

## What is Tratok Hospitality?

Tratok (TRAT) is an ERC-20 utility token founded in 2017 in Dubai by Mohammed Altajir. It powers a blockchain-based hospitality ecosystem connecting travellers directly with hotels, car rentals, restaurants, activities and cruises — cutting the 15–30 % that legacy intermediaries take.

**This repository documents the provider-facing REST API** at [hospitality.tratok.net](https://hospitality.tratok.net) — the surface your PMS / channel manager / back-office uses to manage listings, push pricing, pull bookings, and receive real-time event notifications. Sign in at the provider portal, generate an API key in `Documentation → API`, and start integrating.

## Features

| | |
|---|---|
| 📦 **Inventory Sync** | Push room availability per-day or across a date range in one call |
| 💵 **Dynamic Pricing** | Set per-date prices for any room, single-day or bulk |
| 📅 **Bookings** | Pull every reservation across all your listings, filter by type and status |
| 🔔 **Real-Time Webhooks** | HMAC-signed notifications on booking, payment, review and listing events |
| 📈 **Revenue Reports** | Pre-aggregated gross/net summaries by date and listing |
| 🔄 **Channel Manager** | iCal export + import for Airbnb, Booking.com, Vrbo, Expedia and more |
| 🌐 **i18n First** | Listings and bookings carry data in 16 languages out of the box |
| 🔒 **Secure by Default** | `X-API-Key` header auth, HTTPS only, per-key rate-limited, audit-logged |

## Quick Start

### 1. Get an API key

Sign in at [hospitality.tratok.net](https://hospitality.tratok.net), open **Documentation → API** in the side nav, and click **Generate API Key**. The key + secret are shown once — store them in a secret manager.

### 2. Make your first call

Every endpoint is dispatched off a single `?endpoint=…` query parameter on `https://hospitality.tratok.net/api.php`. Send the key in the `X-API-Key` header.

```bash
curl -X GET "https://hospitality.tratok.net/api.php?endpoint=me" \
  -H "X-API-Key: YOUR_API_KEY"
```

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Smith",
    "email": "john@hotel.com",
    "company": "Grand Hotel Group",
    "country": "United Kingdom",
    "city": "London",
    "email_verified": true,
    "api_rate_limit": 100,
    "member_since": "2025-01-15 10:30:00"
  }
}
```

### 3. Pick your language

Working examples for every endpoint in **[PHP](examples/php)**, **[Node.js](examples/node)**, **[Python](examples/python)** and **[cURL](examples/curl)** live in [`/examples`](examples). Each language bundle ships a thin client, a runnable demo, and a webhook receiver.

## API Modules

| Module | Endpoints | Docs |
|---|---|---|
| 👤 **Account** | `me` · `api_key` | [Read →](docs/modules/me.md) |
| 🏨 **Properties** | `properties` · `rooms` | [Properties →](docs/modules/properties.md) · [Rooms →](docs/modules/rooms.md) |
| 💵 **Pricing & Inventory** | `pricing` · `pricing_bulk` · `inventory` · `inventory_bulk` | [Pricing →](docs/modules/pricing.md) · [Inventory →](docs/modules/inventory.md) |
| 🎯 **Activities** | `activities` | [Read →](docs/modules/activities.md) |
| 🍽️ **Restaurants** | `restaurants` | [Read →](docs/modules/restaurants.md) |
| 📅 **Bookings** | `bookings` | [Read →](docs/modules/bookings.md) |
| 📈 **Revenue** | `revenue` | [Read →](docs/modules/revenue.md) |
| 🔔 **Webhooks** | 8 event types — `booking.*`, `payment.*`, `review.*`, `listing.*` | [Read →](docs/webhooks.md) |
| 🔄 **iCal Sync** | Channel-manager calendar export + import | [Read →](docs/ical-channel-sync.md) |

## Documentation

| | |
|---|---|
| 🚀 [Getting Started](#quick-start) | Account setup, your first API call |
| 🔐 [Authentication](docs/authentication.md) | API keys, rotation, CORS, best practices |
| ⏱ [Rate Limits](docs/rate-limiting.md) | Per-key request quotas and 429 handling |
| ⚠️ [Errors](docs/errors.md) | Error envelope, HTTP codes, retry guidance |
| 📄 [Pagination](docs/pagination.md) | `page` / `limit` and iteration patterns |
| 🔔 [Webhooks](docs/webhooks.md) | Event subscriptions, HMAC signing, retries |
| 🔄 [iCal Channel Sync](docs/ical-channel-sync.md) | Airbnb / Booking.com / Vrbo calendar bridging |
| 📋 [OpenAPI 3.1 Spec](openapi.yaml) | Machine-readable spec — feed into Swagger UI, codegen, etc. |
| 📬 [Postman Collection](postman_collection.json) | One-click import — fill in `{{api_key}}` and you're testing |

## What you can build

- **PMS integrations** — push your nightly rate + availability state into Tratok with two API calls
- **Channel manager bridges** — pair iCal sync with REST writes for sub-30-minute calendar sync
- **Custom dashboards** — pull bookings, revenue and reviews into your own BI / accounting tools
- **Mobile apps for property managers** — read-write access to everything the portal exposes
- **Automated pricing engines** — react to demand signals with `pricing_bulk` range updates
- **AI-driven guest engagement** — webhook-triggered messaging on `booking.confirmed`

## Repository layout

```
.
├── README.md                  ← you are here
├── LICENSE                    ← MIT
├── CHANGELOG.md               ← version history
├── CONTRIBUTING.md            ← how to contribute
├── SECURITY.md                ← reporting vulnerabilities
├── CODE_OF_CONDUCT.md         ← community standards
├── openapi.yaml               ← OpenAPI 3.1 specification
├── postman_collection.json    ← importable Postman v2.1 collection
├── docs/
│   ├── authentication.md
│   ├── rate-limiting.md
│   ├── errors.md
│   ├── pagination.md
│   ├── webhooks.md
│   ├── ical-channel-sync.md
│   └── modules/
│       ├── me.md
│       ├── properties.md
│       ├── rooms.md
│       ├── pricing.md
│       ├── inventory.md
│       ├── activities.md
│       ├── restaurants.md
│       ├── bookings.md
│       └── revenue.md
├── examples/
│   ├── curl/        ← shell recipes for every endpoint
│   ├── python/      ← Python client + demo + webhook receiver
│   ├── node/        ← Node.js client + demo + webhook receiver
│   └── php/         ← PHP client + demo + webhook receiver
└── .github/
    ├── ISSUE_TEMPLATE/
    └── PULL_REQUEST_TEMPLATE.md
```

## Community

- **Provider Portal**: [hospitality.tratok.net](https://hospitality.tratok.net)
- **Main website**: [tratok.net](https://tratok.net)
- **Developer API** (for data queries, not provider management): [developer.tratok.net](https://developer.tratok.net) → [TratokToken/tratok-labs](https://github.com/TratokToken/tratok-labs)
- **Information hub**: [tratok.info](https://tratok.info)
- **Twitter / X**: [@TratokT](https://x.com/TratokT)
- **LinkedIn**: [Tratok Holding Limited](https://www.linkedin.com/company/tratok-ltd/)
- **Issues & feature requests**: open an issue in this repo

## Contributing

This repository is the public source of truth for the Tratok Hospitality API surface. Contributions to the documentation, OpenAPI spec, and example clients are welcome — please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

For security issues, please **do not open a public issue**. Email `security@tratok.com` or follow the disclosure process in [SECURITY.md](SECURITY.md).

## License

The contents of this repository (documentation, OpenAPI spec, example clients) are released under the [MIT License](LICENSE). The Tratok Hospitality API itself is governed by the [Tratok Terms of Service](https://tratok.net/terms.php). Tratok is operated by **Tratok Holding Limited** in Dubai, United Arab Emirates.

---

<div align="center">

© 2017–2026 Tratok Holding Limited · *The world's travel token, since 2017*

</div>

---
