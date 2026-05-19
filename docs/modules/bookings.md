# Module: `bookings`

Read-only access to **every booking** placed against any of your
listings. Filterable by booking type and status. Pulling this endpoint
on a schedule is the polling alternative to subscribing to the
`booking.*` [webhooks](../webhooks.md) — webhooks are strongly
preferred for real-time use cases.

There is no `POST /bookings` — bookings are created by guests on
tratok.net through the payment flow. The provider role on bookings is
read + acknowledge + (eventually) mark as no-show or cancelled, which
happens from the portal at `/booking_calendar.php`.

---

## `GET /api.php?endpoint=bookings`

List bookings, newest first. Paginated.

### Query parameters

| Param | Type | Required | Default | Notes |
| --- | --- | --- | --- | --- |
| `page` | integer | no | `1` | |
| `limit` | integer | no | `20` | Max 100. |
| `type` | string | no | _(all)_ | One of `property`, `activity`, `restaurant`. |
| `status` | string | no | _(all)_ | One of `pending`, `confirmed`, `cancelled`, `completed`, `no_show`. |

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 8214,
      "provider_id": 1,
      "user_id": 421,
      "booking_type": "property",
      "source_id": 24,
      "room_id": 101,
      "package_id": 0,
      "guest_name": "Aïcha Bensalah",
      "guest_email": "aicha@example.com",
      "guest_phone": "+33 6 12 34 56 78",
      "check_in":  "2026-06-04",
      "check_out": "2026-06-07",
      "guests": 2,
      "booking_time": null,
      "notes": "Late check-in, ~22:30",
      "status": "confirmed",
      "total_price": 540.00,
      "currency": "USD",
      "total_trat": 20224.71910,
      "bonus_amount": 5000.00000,
      "onchain_amount": 15224.71910,
      "tx_hash": "0xabc…",
      "payment_expires_at": null,
      "created_at":   "2026-05-19 14:30:00",
      "confirmed_at": "2026-05-19 14:32:00",
      "completed_at": null,
      "cancelled_at": null
    }
  ],
  "pagination": { "total": 87, "page": 1, "limit": 20, "pages": 5 }
}
```

### Status reference

| `status` | Meaning |
| --- | --- |
| `pending` | Initial hold; transitional on new bookings. |
| `pending_payment` | Payment outstanding. Transitions to `confirmed` when payment is confirmed, or `payment_expired` if the deadline lapses. Not in the filter whitelist; pass no `status` and filter client-side to enumerate. |
| `confirmed` | Payment confirmed. Inventory has been decremented. |
| `payment_expired` | A `pending_payment` row that aged past its deadline. Inventory is automatically released. |
| `cancelled` | Cancelled by the guest or provider. |
| `no_show` | Provider-marked from the portal. |
| `completed` | Set 24 hours after `check_out` for bookings that weren't cancelled. |

### Settlement fields

| Field | Type | Notes |
| --- | --- | --- |
| `total_price` | float | Fiat total at booking time, in `currency`. |
| `currency` | string | The fiat currency the room/activity/restaurant is priced in. |
| `total_trat` | decimal | Settlement amount, frozen at the time the booking was confirmed. |
| `bonus_amount` | decimal | Portion paid from the guest's platform bonus balance. |
| `onchain_amount` | decimal | Portion paid via the public Tratok ledger. |
| `tx_hash` | string \| null | Ledger transaction reference when `onchain_amount > 0`. |
| `payment_expires_at` | datetime \| null | Set on `pending_payment` rows; null otherwise. |

For most providers, the fiat `total_price` is what matters for
accounting; the settlement amounts are for auditability.

### Examples

```bash
# All confirmed bookings on properties
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=bookings&type=property&status=confirmed&page=1&limit=50"

# All bookings across all listings (latest first)
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=bookings&page=1&limit=100"
```

---

## `GET /api.php?endpoint=bookings&id=<int>`

Fetch a single booking with the full row (every column on
`bookings`).

### Query parameters

| Param | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | integer | yes | Booking id. Must be for a listing you own. |

### Response

Same shape as the list-row above, with potentially additional columns
(audit fields, internal flags) — your client should accept and ignore
unknown keys.

### Errors

- `404` — booking not yours / doesn't exist.

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=bookings&id=8214"
```

---

## Sync patterns

### Webhook-first

Subscribe to `booking.created` + `booking.confirmed` +
`booking.cancelled` (and `payment.failed` if you care about expired
holds). Each event carries enough payload to mutate your local cache
without an extra API call. See [../webhooks.md](../webhooks.md).

### Polling-fallback

If you can't accept webhooks, poll `bookings?status=confirmed` once per
minute and key on `id`. Keep the highest-id seen so subsequent polls
can short-circuit. With 100 bookings/page and a 1-per-minute schedule
you'll be well inside the rate limit.

### Status change detection

The `bookings` row includes `*_at` timestamps but **not** an
"updated_at" — you can't sort by "recently changed". For change
detection, subscribe to `booking.modified` (webhook) or compare against
your local snapshot.
