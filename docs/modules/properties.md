# Module: `properties`

Read-only access to your **accommodation listings** (hotels, villas,
apartments, B&Bs, hostels — anything that has rooms).

Listing creation, photo uploads, and content edits go through the
portal (`/manage_assets.php`) because they require image review and a
human approval step.

---

## `GET /api.php?endpoint=properties`

List every property owned by the authenticated provider. Paginated.

### Query parameters

| Param | Type | Required | Default | Max | Notes |
| --- | --- | --- | --- | --- | --- |
| `page` | integer | no | `1` | — | 1-based |
| `limit` | integer | no | `20` | `100` | Server-clamped |

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 24,
      "name": "Grand Plaza Hotel",
      "city": "Dubai",
      "country": "UAE",
      "picture": "uploads/properties/prop_1234.jpg",
      "approved": true,
      "deactivated": false,
      "views": 1250,
      "bookings": 87,
      "rating": 4.60,
      "created_at": "2025-01-20 14:00:00"
    }
  ],
  "pagination": { "total": 3, "page": 1, "limit": 20, "pages": 1 }
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `id` | integer | Property id; pass to `?endpoint=rooms&property_id=…` and `?endpoint=bookings&type=property&...` |
| `name` | string | Display name (provider-edited, may be translated for tratok.net viewers). |
| `city` / `country` | string | Free-text; not normalized to ISO codes. |
| `picture` | string | Hero image, relative to `https://hospitality.tratok.net/`. Always JPG/PNG; the public site serves a WebP/AVIF twin via Accept negotiation. |
| `approved` | boolean | False until the Tratok review team approves. Unapproved listings don't show on tratok.net. |
| `deactivated` | boolean | Provider-toggled; hides the listing without deleting it. |
| `views` | integer | Lifetime view count from tratok.net. |
| `bookings` | integer | Lifetime confirmed-or-completed bookings. |
| `rating` | float | Average review rating, 0–5, two decimals. `0.00` if no reviews. |
| `created_at` | string | UTC `YYYY-MM-DD HH:MM:SS`. |

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=properties&page=1&limit=20"
```

---

## `GET /api.php?endpoint=properties&id=<int>`

Fetch one property with full detail.

### Query parameters

| Param | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | integer | yes | The property id. |

### Response

```json
{
  "success": true,
  "data": {
    "id": 24,
    "name": "Grand Plaza Hotel",
    "description": "A luxury 5-star hotel in the heart of Dubai...",
    "address": "123 Sheikh Zayed Rd",
    "city": "Dubai",
    "country": "UAE",
    "number": "+971 4 123 4567",
    "picture": "uploads/properties/prop_1234.jpg",
    "maps": "25.2048, 55.2708",
    "reservationsEmail": "reservations@grandplaza.example",
    "approved": true,
    "deactivated": false,
    "views": 1250,
    "bookings": 87,
    "rating": 4.60,
    "created_at": "2025-01-20 14:00:00",
    "updated_at": "2026-04-12 09:30:00"
  }
}
```

Additional fields over the list response:

| Field | Type | Notes |
| --- | --- | --- |
| `description` | string | Long-form description, may contain newlines. Provider-edited. |
| `address` | string | Street address (free-text). |
| `number` | string | Phone, internationally formatted. |
| `maps` | string | `"lat, lng"` — comma-and-space separated. Parse with `lat, lng = [float(p.strip()) for p in s.split(',')]`. Empty if not set. |
| `reservationsEmail` | string \| null | Optional separate email for booking notifications (else `email` from `me`). |
| `updated_at` | string \| null | UTC; null on rows untouched since migration. |

### Errors

- `404` — id doesn't exist OR isn't yours. (No distinction by design.)

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=properties&id=24"
```
