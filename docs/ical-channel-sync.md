# iCal channel sync

For providers who also list on Airbnb / Booking.com / Vrbo / Expedia
etc., Tratok exposes a per-room **iCal feed** so external platforms can
read Tratok's booked dates, and accepts an **import URL** so Tratok
reads back the external platform's booked dates. The two feeds together
prevent double-booking without a full channel-manager integration.

This is a separate surface from the REST API:

- **Export** is served by `https://hospitality.tratok.net/ical_export.php?token=…`
  (no key — the token IS the auth).
- **Import** URLs are stored against a room and polled by Tratok every
  ~30 minutes.

Setup lives in the portal at `/channel_sync.php`.

---

## Generating an export URL

In `/channel_sync.php`:

1. **Add Channel** → choose the listing (property or activity) and,
   optionally, a specific room.
2. Pick a channel name (Airbnb, Booking.com, Vrbo, Expedia,
   TripAdvisor, Google, Other).
3. **Save**.

You'll see a row like:

```
Channel: Airbnb · Room 101
Export URL: https://hospitality.tratok.net/ical_export.php?token=f4e2…
Import URL: (paste your Airbnb iCal link here, optional)
```

Copy the export URL into the external platform's "Sync calendar"
field. The token is 64-char hex, single-purpose, scoped to that one
channel row. Revoking is a delete from `/channel_sync.php`.

The export feed includes:

- All non-cancelled Tratok bookings.
- Each event spans `check_in 00:00` to `check_out 00:00` in the
  listing's timezone.
- `SUMMARY: Tratok booking #<id>` (no guest PII — channel partners see
  only "blocked").

---

## Adding an import URL

Paste an external iCal URL (e.g. Airbnb's "Export Calendar" link) into
the **Import URL** field of the same channel row. Tratok will:

- Fetch the URL on a ~30-minute interval.
- Refuse to fetch from non-public hosts (SSRF guard) and non-HTTP(S)
  schemes.
- Apply each external event as a Tratok **inventory block** for the
  matching room, with `source = 'external_<channel>'`.

External blocks show up in `/booking_calendar.php` greyed out and in
the API's `inventory` response as `available = 0` for the affected
dates.

---

## Limits and gotchas

- One export URL per `(provider, source_type, source_id, room_id)` row;
  delete and re-add to rotate the token.
- Maximum 20 channel sync rows per provider.
- The poller honors `CACHE-CONTROL: max-age` / `EXPIRES` headers in
  iCal responses up to 30 minutes max; partners that don't set them
  get hit every 30 min anyway.
- Time zones default to the listing's `timezone` field; if missing,
  `Asia/Dubai` (Tratok HQ TZ) is assumed — set the timezone on the
  listing to avoid off-by-one errors at midnight.

---

## When to use channel sync vs the API

| Goal | Use |
| --- | --- |
| Push Tratok bookings into OTAs that consume iCal. | iCal export. |
| Pull OTA bookings into Tratok so the room shows unavailable. | iCal import. |
| Real-time, event-driven booking sync (created / cancelled / modified). | [Webhooks](webhooks.md). |
| Programmatic price + inventory writes from your PMS. | REST `pricing` / `inventory` endpoints. |
| Full PMS replacement that doesn't speak iCal. | REST `bookings` GET + webhooks for state changes. |

iCal is the lowest-friction option but is **read-only at 30-minute
granularity** — for high-turnover listings (activities, restaurants),
prefer the REST API.
