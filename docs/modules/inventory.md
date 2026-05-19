# Module: `inventory` / `inventory_bulk`

Read and write **per-date room availability** — the count of how many
units of a room type can be sold on a given date. UPSERT semantics on
`(room_id, date)`.

When a date has no `room_inventory` row, the room's `numberOfRooms` (see
[rooms.md](rooms.md)) is the implicit maximum.

> **Booking races.** Tratok's reservation flow decrements available
> inventory atomically at booking confirmation. A `POST /inventory` call
> sets the absolute available count — it does NOT increment / decrement.
> If you've sold 2 of 5 today and call `inventory` with `available: 5`,
> you've effectively re-listed those 2 already-booked units. Use the
> `bookings` endpoint or webhooks to know what's already sold before
> writing back inventory.

---

## `GET /api.php?endpoint=inventory&room_id=<int>[&from=YYYY-MM-DD][&to=YYYY-MM-DD]`

Read inventory rows in a date range. Like `pricing`, only dates with an
explicit `room_inventory` row are returned; implicit-default dates are
not.

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
    { "date": "2026-06-04", "available": 5 },
    { "date": "2026-06-05", "available": 3 },
    { "date": "2026-06-06", "available": 0 }
  ]
}
```

`available: 0` means **fully booked / blocked** — tratok.net hides the
room for that date.

### Errors

- `400` — `room_id` missing.
- `404` — room not yours.

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=inventory&room_id=101&from=2026-06-01&to=2026-06-30"
```

---

## `POST /api.php?endpoint=inventory`

Set the absolute available count for a single date.

### Request body

```json
{
  "room_id": 101,
  "date": "2026-06-04",
  "available": 5
}
```

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `room_id` | integer | yes | |
| `date` | date | yes | `YYYY-MM-DD`. |
| `available` | integer | yes | ≥ 0. Server does NOT validate against `numberOfRooms`; values above it are accepted but tratok.net clamps. |

### Response

```json
{
  "success": true,
  "data": {
    "room_id": 101,
    "date": "2026-06-04",
    "available": 5,
    "message": "Inventory updated."
  }
}
```

### Errors

- `400` — missing/invalid `room_id`, `date`, or `available < 0`.
- `404` — room not yours.

### Examples

```bash
# Block the room for a day
curl -X POST \
     -H "X-API-Key: $KEY" \
     -H "Content-Type: application/json" \
     -d '{"room_id":101,"date":"2026-06-04","available":0}' \
     "https://hospitality.tratok.net/api.php?endpoint=inventory"
```

---

## `POST /api.php?endpoint=inventory_bulk`

Set the same `available` count across a date range. One request,
one rate-limit slot, N UPSERTs server-side.

### Request body

```json
{
  "room_id": 101,
  "start_date": "2026-06-01",
  "end_date": "2026-06-30",
  "available": 5
}
```

### Response

```json
{
  "success": true,
  "data": {
    "room_id": 101,
    "days_updated": 30,
    "available": 5
  }
}
```

### Use cases

- Open a season: `inventory_bulk` with `available = numberOfRooms` for
  the whole season.
- Close a season: `inventory_bulk` with `available = 0`.
- Maintenance window: `inventory_bulk` with `available = 0` for the
  closure dates, then `inventory_bulk` back to normal for the reopen.

### Errors

- `400` — missing/invalid params, or `available < 0`.
- `404` — room not yours.

### Examples

```bash
# Open the room with 5 units available across the whole month
curl -X POST \
     -H "X-API-Key: $KEY" \
     -H "Content-Type: application/json" \
     -d '{
       "room_id": 101,
       "start_date": "2026-06-01",
       "end_date":   "2026-06-30",
       "available":  5
     }' \
     "https://hospitality.tratok.net/api.php?endpoint=inventory_bulk"
```

---

## Sync patterns

### Daily push from your PMS

A common pattern: every morning, push the next 365 days of pricing +
inventory.

```python
from datetime import date, timedelta
from tratok_client import TratokClient

client = TratokClient(api_key=os.environ["TRATOK_API_KEY"])

today = date.today()
horizon = today + timedelta(days=365)

# 1. Pull current bookings so you don't overwrite sold inventory.
bookings = client.list_all("bookings", params={"type": "property", "status": "confirmed"})
sold_per_date = compute_sold_per_date(bookings, room_id=101)

# 2. Decide tomorrow's open inventory.
for d in iter_days(today, horizon):
    physical = 5
    sold     = sold_per_date.get(d, 0)
    open_inv = max(0, physical - sold)
    client.post("inventory", json={"room_id": 101, "date": d.isoformat(), "available": open_inv})
```

For volume, prefer collapsing contiguous identical days into
`inventory_bulk` calls.

### Receiving an OTA cancellation (Airbnb iCal pull → free up Tratok)

Tratok's iCal poller handles this automatically — see
[ical-channel-sync.md](../ical-channel-sync.md). You only need direct
API writes if your sync window is sub-30-minute.
