# Rate limiting

Every API key has a **per-minute request quota**. The default is
**100 requests / minute**; a higher limit is available on request for
production integrations — email `api-support@tratok.net` with your
expected request volume and pattern.

The current quota for a key is visible in the `me` response:

```json
{ "data": { "api_rate_limit": 100, ... } }
```

---

## How it's measured

The limiter is a sliding 60-second window keyed on the API key:

- Each request, success or failure, counts.
- The window starts ticking from the **first** request in the last
  60 seconds.
- When the count hits the limit, every subsequent request is rejected
  with `429` for the rest of the window. Once a previous request ages
  past 60 seconds, you regain that slot.

There is no per-endpoint sub-limit today — `GET /me` and
`POST /pricing_bulk` cost the same one slot each.

---

## What 429 looks like

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 60
Content-Type: application/json

{
  "success": false,
  "error": "Rate limit exceeded. Maximum 100 requests per minute."
}
```

The `Retry-After: 60` header is a conservative ceiling — you can usually
retry sooner as old requests fall out of the window. A reasonable
back-off:

1. On first `429`, wait the `Retry-After` value.
2. On the next `429` in the same hour, double the wait, capped at
   5 minutes.
3. Log and alert if `429`s persist past 15 minutes — you probably need
   a higher limit, not more retries.

---

## Best practices

- **Batch where possible.** Use `pricing_bulk` and `inventory_bulk`
  instead of one POST per date.
- **Cache reads.** `me`, `properties`, `rooms` rarely change minute-to-
  minute — cache them on your side for 5-15 minutes.
- **Subscribe to webhooks** instead of polling `bookings`. A polling
  loop checking every 10s burns 360 requests/hour per integration; a
  webhook costs zero outbound requests.
- **Spread bulk syncs.** If you're rebuilding a year of pricing on
  startup, throttle to ~1 req/sec yourself rather than racing into the
  limit and back-off-looping.

---

## Asking for a higher limit

Send `api-support@tratok.net`:

- Your provider account email.
- The peak rate you need (requests per minute) and a typical week's
  shape (e.g. "bursts at 02:00 UTC for nightly sync, ~5 req/sec for
  10 min, then quiet").
- Whether you'd accept a higher limit on read-only endpoints only.

A bump to 300/min for read endpoints is usually approved within one
business day for active providers.
