# Errors

Every error response carries an HTTP status code AND a JSON body:

```json
{
  "success": false,
  "error": "Human-readable description of what went wrong."
}
```

The `error` string is intended for engineering logs, not end-user
display — wording may change without notice. Use the **status code** to
branch your client logic.

---

## HTTP status code reference

| Status | When | Recover by |
| --- | --- | --- |
| `400 Bad Request` | A required parameter is missing or fails validation. | Fix the request body / query string. |
| `401 Unauthorized` | Missing `X-API-Key`, key is unknown, or `api_enabled = 0`. | Re-issue or re-enable the key. |
| `404 Not Found` | The endpoint name is unknown, OR the resource id is not yours / not in the database. | Double-check the `endpoint` and the resource ownership. |
| `405 Method Not Allowed` | You sent `POST` to a `GET`-only endpoint (or vice versa). | Use the documented method. |
| `429 Too Many Requests` | Per-key rate limit exhausted. | Wait `Retry-After` seconds, then retry. See [rate-limiting.md](rate-limiting.md). |
| `500 Internal Server Error` | Unexpected server fault. | Retry with exponential back-off; if it persists, contact support. |

The API never returns `3xx` redirects. Treat any `3xx` as an
infrastructure misconfiguration and contact support.

---

## Common error messages

### `"Missing API key. Send it in the X-API-Key request header."` (401)
You didn't send `X-API-Key`. Query-string keys (`?api_key=…`) are no
longer accepted.

### `"Invalid or disabled API key."` (401)
The key doesn't match any record, the provider's `status` is not
`active`, or `api_enabled = 0`.

### `"Rate limit exceeded. Maximum N requests per minute."` (429)
See [rate-limiting.md](rate-limiting.md). The `Retry-After` header tells
you how long to wait.

### `"Method not allowed. Use GET."` / `"Use POST."` / `"Use GET or POST."` (405)
Each endpoint documents its allowed methods. `pricing` and `inventory`
accept both `GET` and `POST`; everything else is one or the other.

### `"Unknown endpoint: '...'. See API documentation."` (404)
Typos in the `endpoint` query parameter, or you're aiming at a
deprecated name. Cross-check against the
[README endpoint summary](../README.md#endpoint-summary).

### `"<resource> not found."` (404)
The resource exists but isn't owned by your provider account, or it
doesn't exist at all. The API does not distinguish between the two — by
design, to avoid leaking other providers' resource ids.

### `"<field> is required."` / `"Valid <field> required."` (400)
The named field is missing or fails format validation
(e.g. dates not matching `YYYY-MM-DD`, prices < 0).

---

## Retrying

Retries are **safe** on:
- All `GET` requests.
- `POST /pricing` and `POST /pricing_bulk` — idempotent (UPSERT on
  `(room_id, date)`).
- `POST /inventory` and `POST /inventory_bulk` — idempotent (UPSERT on
  `(room_id, date)`).

Retries are **not** appropriate on:
- `POST /api_key` — this rotates the key. Retrying after a successful
  response would generate a second new key and invalidate the one you
  just received. If the response was lost mid-flight, the safest path is
  to rotate again from the dashboard once and use that key.

Suggested retry policy for transient errors (`5xx`, network drops):
exponential back-off starting at 500ms, capped at 60s, max 5 attempts.

---

## Reporting issues

When emailing support about an error, include:

- The HTTP status and the `error` string.
- The full request URL **with the API key redacted**.
- The request body (for `POST`s) with PII redacted.
- The UTC timestamp of the attempt (±5 seconds is fine).
- Your provider account email.

This lets us correlate to the `api_request_log` row server-side and
diagnose without a back-and-forth.
