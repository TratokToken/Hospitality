# Module: `pricing` / `pricing_bulk`

Read and write **per-date room prices**. Writes are idempotent
UPSERTs on `(room_id, date)`.

When a date has no `room_pricing` row, the room's default `price` (from
[`rooms`](rooms.md)) applies on tratok.net.

> **Currency note:** Prices set via this API are in fiat
> (`currency`, defaulting to the room's currency). The platform handles
> conversion to TRAT at booking time and freezes the rate for the
> duration of checkout. The settlement amount can vary slightly
> day-to-day for the same fiat price as market rates move; the
> per-booking row records both the fiat `total_price` and the
> settlement `total_trat`.

---

## `GET /api.php?endpoint=pricing&room_id=<int>[&from=YYYY-MM-DD][&to=YYYY-MM-DD]`

Read prices over a date range. Returns only dates that have an explicit
`room_pricing` row — implicit "default" dates aren't echoed.

### Query parameters

| Param | Type | Required | Default | Notes |
| --- | --- | --- | --- | --- |
| `room_id` | integer | yes | — | |
| `from` | date | no | today | `YYYY-MM-DD` |
| `to` | date | no | today + 30 days | `YYYY-MM-DD`, inclusive |

### Response

```json
{
  "success": true,
  "data": [
    { "date": "2026-06-04", "price": 219.00 },
    { "date": "2026-06-05", "price": 219.00 },
    { "date": "2026-06-06", "price": 249.00 }
  ]
}
```

`data` is sorted by `date ASC`.

### Errors

- `400` — `room_id` missing.
- `404` — room not yours.

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=pricing&room_id=101&from=2026-06-01&to=2026-06-30"
```

---

## `POST /api.php?endpoint=pricing`

Set the price for a single `(room_id, date)`. UPSERT — overwrites any
existing value for that pair.

### Request body

Either JSON (preferred) or form-encoded.

```json
{
  "room_id": 101,
  "date": "2026-06-04",
  "price": 219.00
}
```

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `room_id` | integer | yes | Room must be yours. |
| `date` | date | yes | `YYYY-MM-DD`. |
| `price` | float | yes | ≥ 0. Use `0` to mark "complimentary" (tratok.net still shows the room with TRAT total 0). To make a date unbookable, see [inventory.md](inventory.md). |

### Response

```json
{
  "success": true,
  "data": {
    "room_id": 101,
    "date": "2026-06-04",
    "price": 219.00,
    "message": "Price updated."
  }
}
```

### Errors

- `400` — missing/invalid `room_id`, `date`, or `price < 0`.
- `404` — room not yours.

### Examples

```bash
curl -X POST \
     -H "X-API-Key: $KEY" \
     -H "Content-Type: application/json" \
     -d '{"room_id":101,"date":"2026-06-04","price":219.00}' \
     "https://hospitality.tratok.net/api.php?endpoint=pricing"
```

---

## `POST /api.php?endpoint=pricing_bulk`

Set the same price across a range of dates. Internally executes one
UPSERT per day; `days_updated` in the response is the count actually
written.

### Request body

```json
{
  "room_id": 101,
  "start_date": "2026-06-01",
  "end_date": "2026-06-30",
  "price": 199.00
}
```

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `room_id` | integer | yes | |
| `start_date` | date | yes | `YYYY-MM-DD`, inclusive. |
| `end_date` | date | yes | `YYYY-MM-DD`, inclusive. Must be ≥ `start_date`. |
| `price` | float | yes | ≥ 0; same price applies to every day in the range. |

For different prices per day, send multiple `pricing` POSTs or call
`pricing_bulk` once per band.

### Response

```json
{
  "success": true,
  "data": {
    "room_id": 101,
    "days_updated": 30,
    "price": 199.00
  }
}
```

### Errors

- `400` — missing/invalid `room_id`, `start_date`, `end_date`, or
  `price < 0`.
- `404` — room not yours.

### Rate-limit note

A `pricing_bulk` call counts as **one** request against your rate-limit
budget, regardless of how many dates it covers. Prefer it over loops of
single-day POSTs whenever the price is uniform.

### Examples

```bash
curl -X POST \
     -H "X-API-Key: $KEY" \
     -H "Content-Type: application/json" \
     -d '{
       "room_id": 101,
       "start_date": "2026-06-01",
       "end_date":   "2026-06-30",
       "price":      199.00
     }' \
     "https://hospitality.tratok.net/api.php?endpoint=pricing_bulk"
```

---

## Patterns

### Seasonal pricing in one call per band

```bash
# Low season
curl -X POST -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
     -d '{"room_id":101,"start_date":"2026-04-15","end_date":"2026-06-14","price":149.00}' \
     "https://hospitality.tratok.net/api.php?endpoint=pricing_bulk"

# Peak season
curl -X POST -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
     -d '{"room_id":101,"start_date":"2026-06-15","end_date":"2026-09-15","price":249.00}' \
     "https://hospitality.tratok.net/api.php?endpoint=pricing_bulk"
```

### Reverting a date to "use default room price"

There's no DELETE for individual `room_pricing` rows via the API today.
The two options:

1. Set the price back to the room's default value via POST — semantically
   the same on tratok.net.
2. Delete from the portal at `/room_dashboard.php` (Calendar view → click
   the date → Reset).

A `DELETE` method on `pricing` is on the roadmap.
