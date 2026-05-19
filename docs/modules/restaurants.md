# Module: `restaurants`

Read-only access to your **restaurant listings**. Restaurants are
reservation-based — guests book a table for a date + time + party
size. The listing carries a cuisine type, a price range bucket, and an
optional set of packages.

Content management lives in `/manage_restaurants.php`.

---

## `GET /api.php?endpoint=restaurants`

List all restaurants owned by the authenticated provider. Paginated.

### Query parameters

| Param | Type | Required | Default | Max |
| --- | --- | --- | --- | --- |
| `page` | integer | no | `1` | — |
| `limit` | integer | no | `20` | `100` |

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "name": "Aurora Fine Dining",
      "cuisine_type": "Mediterranean",
      "city": "Dubai",
      "country": "UAE",
      "picture": "uploads/restaurants/rest_42.jpg",
      "price_range": "$$$",
      "approved": true,
      "deactivated": false,
      "created_at": "2025-04-02 14:00:00"
    }
  ],
  "pagination": { "total": 2, "page": 1, "limit": 20, "pages": 1 }
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `id` | integer | Restaurant id. |
| `name` | string | Display name. |
| `cuisine_type` | string | Free-text (Mediterranean, Japanese, etc.). |
| `picture` | string | Hero image. |
| `price_range` | string | One of `$`, `$$`, `$$$`, `$$$$` — provider-set. |
| `approved` / `deactivated` | boolean | Same semantics as properties. |
| `created_at` | string | UTC. |

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=restaurants&page=1&limit=20"
```

---

## `GET /api.php?endpoint=restaurants&id=<int>`

Fetch one restaurant with full detail.

### Response

```json
{
  "success": true,
  "data": {
    "id": 42,
    "name": "Aurora Fine Dining",
    "description": "Modern Mediterranean tasting menu with sea views...",
    "cuisine_type": "Mediterranean",
    "address": "Pearl Promenade 14",
    "city": "Dubai",
    "country": "UAE",
    "number": "+971 4 555 8888",
    "maps": "25.0820, 55.1380",
    "picture": "uploads/restaurants/rest_42.jpg",
    "contact_email": "reserve@aurora.example",
    "price_range": "$$$",
    "approved": true,
    "deactivated": false,
    "views": 1880,
    "bookings": 92,
    "rating": 4.71,
    "created_at": "2025-04-02 14:00:00"
  }
}
```

Additional fields:

| Field | Type | Notes |
| --- | --- | --- |
| `description` | string | Long-form. |
| `address`, `maps` | string | Same shape as properties. |
| `number`, `contact_email` | string | Booking-contact info. |
| `views`, `bookings`, `rating` | numbers | Lifetime stats. |

### Errors

- `404` — not yours / doesn't exist.

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=restaurants&id=42"
```

---

## Packages and menu

Restaurant **packages** (e.g. "5-course tasting menu, USD 120 per
person") and **menu items** are managed in `/manage_restaurants.php`
and surfaced on tratok.net but are not exposed via the REST API today.
If you need programmatic access, email `api-support@tratok.net`.

## À-la-carte bookings

Restaurant reservations with no package selected (à-la-carte, "charged
at the table") arrive via `bookings` with `total_price = 0` and
`total_trat = 0`. They still take the table — payment happens off-
platform on arrival.
