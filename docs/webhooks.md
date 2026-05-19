# Webhooks

Webhooks deliver real-time event notifications from Tratok to a URL you
control. Use them instead of polling `bookings` for status changes — a
polling client uses dozens of API calls per minute; a webhook costs you
zero outbound requests.

---

## Registering a webhook

Webhook subscriptions are managed in the **portal** (not the REST API):

1. Sign in at [hospitality.tratok.net](https://hospitality.tratok.net).
2. Open `/webhooks.php` from the side navigation.
3. **Add Webhook** → paste your HTTPS endpoint URL, tick the events to
   subscribe to.
4. Tratok displays the **signing secret** once — save it. This secret is
   per-webhook (different from your API key's secret).

Constraints:

- URL must be **HTTPS** with a public hostname. Localhost, RFC 1918
  private ranges, and loopback addresses are rejected (SSRF guard).
- Maximum **10 webhooks per provider account**.
- Each webhook can subscribe to any subset of the event types below.

---

## Event types

| Event | Fires when | Typical payload `data` keys |
| --- | --- | --- |
| `booking.created` | A booking row is inserted (any initial status). | `booking_id`, `booking_type`, `source_id`, `status`, `guest_*`, `check_in`, `check_out`, `total_price`, `total_trat`, `currency` |
| `booking.confirmed` | Status transitions to `confirmed`. | Same as above + `confirmed_at` |
| `booking.cancelled` | Guest or provider cancels. | Same as above + `cancelled_at`, `refund_trat` |
| `booking.modified` | Provider edits booking fields. | Updated booking row |
| `payment.processed` | Payment for a booking is confirmed. | `booking_id`, `amount_trat`, `confirmed_at` |
| `payment.failed` | A booking ages past its payment deadline without confirmed payment. | `booking_id`, `expired_at` |
| `review.created` | Guest leaves a review. | `review_id`, `source_type`, `source_id`, `rating`, `body`, `author_name` |
| `listing.updated` | Provider edits a property/activity/restaurant in the portal. | `listing_type`, `listing_id`, `changed_fields` |

> **Status sequencing:** A booking with a deferred-payment leg arrives
> via `booking.created` first, then either `booking.confirmed` (once
> payment is confirmed) or `payment.failed` (if it expires). Only treat
> the reservation as final on `booking.confirmed`.

---

## Payload shape

POSTed as `application/json; charset=utf-8`:

```json
{
  "event":     "booking.confirmed",
  "timestamp": "2026-05-19T14:32:01Z",
  "data": {
    "booking_id": 8214,
    "booking_type": "property",
    "source_id": 24,
    "status": "confirmed",
    "guest_name": "Aïcha Bensalah",
    "guest_email": "aicha@example.com",
    "check_in": "2026-06-04",
    "check_out": "2026-06-07",
    "total_price": 540.00,
    "currency": "USD",
    "total_trat": 20224.71910,
    "confirmed_at": "2026-05-19T14:32:00Z"
  }
}
```

`event` and `timestamp` are stable; `data` shape depends on the event
type.

---

## Verifying the signature

Every delivery carries an `X-Webhook-Signature` header:

```
X-Webhook-Signature: 9c7f4e2b5a1d3e8f6a0b7c4d9e2f1a8b5c3d7e4f1a8b2c9d6e3f0a7b4c1d8e5f
```

It is the lowercase hex of `HMAC-SHA256(secret, raw_body)`, where:

- `secret` is the per-webhook secret shown on creation.
- `raw_body` is the unparsed request body **byte-for-byte** — verify
  before JSON-decoding, otherwise re-serialization will change the bytes.

Verification (Python):

```python
import hmac, hashlib

def verify(secret: str, raw_body: bytes, signature: str) -> bool:
    expected = hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, signature)
```

Verification (Node.js):

```js
const crypto = require('crypto');

function verify(secret, rawBody, signature) {
  const expected = crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
  // timingSafeEqual requires equal-length buffers
  if (expected.length !== signature.length) return false;
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature));
}
```

Verification (PHP):

```php
function verify(string $secret, string $rawBody, string $signature): bool {
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signature);
}
```

Reject any request that fails verification. Treat a missing
`X-Webhook-Signature` header the same way.

---

## Delivery + retries

Tratok considers a delivery successful when your endpoint returns a
**2xx** status within **10 seconds**.

| Outcome | Behavior |
| --- | --- |
| 2xx | Marked delivered. `webhook_log.delivered = 1`. |
| 4xx | Marked failed. **No retry** — 4xx implies bad-request, not transient. Fix the consumer. |
| 5xx or timeout | Retried with exponential back-off: 1m, 5m, 30m, 2h, 8h. Max 5 attempts. |
| `is_active = 0` | Skipped. |

After 5 consecutive failures, the webhook is auto-disabled and the
provider is notified via portal flash on next login. Re-enable from
`/webhooks.php` once the consumer is fixed.

The portal's **Delivery Log** (last 30 attempts per provider) shows
timestamp, event, HTTP status, attempt count.

---

## Idempotency

Tratok sends each event with a stable shape but does **not** include an
idempotency id today. To handle the rare case of a duplicate delivery
(retry after a transient connection drop where your handler actually
succeeded but the response was lost):

- For `booking.*` events, key on `(event, booking_id, timestamp)` —
  the timestamp is stable for a given event.
- For `review.*`, key on `(event, review_id)`.
- For `payment.*`, key on `(event, booking_id, timestamp)`.

A dedicated `event_id` field will be added in a future release; treat
unknown top-level keys as additive (don't reject the payload).

---

## Testing locally

1. Use a tunnelling tool ([`ngrok`](https://ngrok.com),
   [`cloudflared tunnel`](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/),
   or [`tailscale funnel`](https://tailscale.com/kb/1223/tailscale-funnel))
   to expose `https://your-tunnel/webhooks` to a local server.
2. Register the public tunnel URL in `/webhooks.php`.
3. Trigger a test event (create a low-value booking on a test listing).
4. Inspect the request in your local logs, verify the signature.

Tratok does not currently provide a "send test event" button — the
roadmap has one. Until then, the lowest-friction trigger is a real
booking on a test property priced at zero.

---

## Rotating a webhook secret

Click the key icon next to the webhook in `/webhooks.php`. A new secret
is displayed; the old one stops working immediately. Plan deployments
so your verifier is reading the new secret before you rotate.
