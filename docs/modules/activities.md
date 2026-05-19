# Module: `activities`

Read-only access to your **activity / experience listings** — guided
tours, classes, rentals, anything bookable on a per-person, per-date
basis.

Activities don't have rooms or per-date pricing — the listing carries a
single `price` field and a `max_participants` cap. Content management
goes through `/manage_activities.php`.

---

## `GET /api.php?endpoint=activities`

List all activities owned by the authenticated provider. Paginated.

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
      "id": 87,
      "name": "Sunset Desert Safari",
      "category": "outdoor",
      "price": 95.00,
      "currency": "USD",
      "city": "Dubai",
      "country": "UAE",
      "picture": "uploads/activities/act_87.jpg",
      "approved": true,
      "deactivated": false,
      "created_at": "2025-03-10 09:00:00"
    }
  ],
  "pagination": { "total": 4, "page": 1, "limit": 20, "pages": 1 }
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `id` | integer | Activity id. |
| `name` | string | Display name. |
| `category` | string | Free-form provider categorization (`outdoor`, `wellness`, `culture`, `food`, etc.). Not validated. |
| `price` | float | Per-person price in `currency`. |
| `currency` | string | ISO 4217. |
| `city`/`country` | string | Free-text. |
| `picture` | string | Hero image. |
| `approved` | boolean | Tratok review state. |
| `deactivated` | boolean | Provider toggle. |
| `created_at` | string | UTC. |

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=activities&page=1&limit=20"
```

---

## `GET /api.php?endpoint=activities&id=<int>`

Fetch one activity with full detail.

### Response

```json
{
  "success": true,
  "data": {
    "id": 87,
    "name": "Sunset Desert Safari",
    "description": "A 4-hour 4x4 trip across the dunes, ending with a Bedouin-style dinner under the stars...",
    "category": "outdoor",
    "duration_hours": 4,
    "price": 95.00,
    "currency": "USD",
    "max_participants": 12,
    "address": "Al Lahbab Desert Conservation Reserve",
    "city": "Dubai",
    "country": "UAE",
    "maps": "24.7806, 55.6011",
    "picture": "uploads/activities/act_87.jpg",
    "contact_email": "bookings@desertsafari.example",
    "contact_phone": "+971 50 123 4567",
    "approved": true,
    "deactivated": false,
    "views": 3200,
    "bookings": 145,
    "rating": 4.83,
    "created_at": "2025-03-10 09:00:00"
  }
}
```

Additional fields over the list response:

| Field | Type | Notes |
| --- | --- | --- |
| `description` | string | Long-form. |
| `duration_hours` | integer | Round numbers; provider convention. |
| `max_participants` | integer | Hard cap per booking. tratok.net's booking form caps the participants dropdown at this value. |
| `address`, `maps` | string | Same shape as properties — `maps` is `"lat, lng"`. |
| `contact_email`, `contact_phone` | string | Optional booking-contact info. |
| `views`, `bookings`, `rating` | numbers | Lifetime stats. |

### Errors

- `404` — not yours / doesn't exist.

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=activities&id=87"
```

---

## What's not exposed

Activities have no `pricing` / `inventory` endpoints today — capacity is
the single `max_participants` cap, and pricing is the single `price`
field per listing. If you need per-date pricing on activities (e.g.
weekend premium), email `api-support@tratok.net` — it's on the roadmap.
