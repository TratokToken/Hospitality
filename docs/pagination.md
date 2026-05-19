# Pagination

Endpoints that return collections (`properties`, `activities`,
`restaurants`, `bookings`) use a simple page-and-limit scheme.

---

## Request

| Param | Type | Default | Max | Notes |
| --- | --- | --- | --- | --- |
| `page` | integer | `1` | unlimited | 1-based |
| `limit` | integer | `20` | `100` | Server clamps to the max |

Example:

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=properties&page=2&limit=50"
```

---

## Response

Paginated responses extend the standard envelope with a `pagination` block:

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "total": 137,
    "page":  2,
    "limit": 50,
    "pages": 3
  }
}
```

| Field | Meaning |
| --- | --- |
| `total` | Total matching rows across all pages. |
| `page`  | The page you just received (echoes your request). |
| `limit` | The limit applied (server-side clamped, may differ from your request). |
| `pages` | `ceil(total / limit)` — the highest valid `page` value. |

---

## Ordering

The order is **stable per endpoint** and documented per module:

- `properties` / `activities` / `restaurants`: `created_at DESC` (newest
  first).
- `bookings`: `created_at DESC`.
- `rooms`: `name ASC`.
- `pricing` / `inventory`: `date ASC`.

There is no opt-in alternative ordering today.

---

## When pagination is missing

Endpoints that always return a small bounded set (`rooms` for a given
property, `pricing`/`inventory` for a date range) return the full result
without a `pagination` block. Bound those queries with `from` / `to` for
date-windowed endpoints to keep response size reasonable.

---

## Iterating safely

```python
page, limit = 1, 100
while True:
    r = client.get("properties", params={"page": page, "limit": limit})
    for prop in r["data"]:
        process(prop)
    if page >= r["pagination"]["pages"]:
        break
    page += 1
```

If new items can be created during iteration (e.g. someone creates a
booking while you're paginating `bookings`), expect the same booking to
possibly appear on two pages. Tolerate duplicates idempotently in your
consumer.
