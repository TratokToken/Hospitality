# Authentication

The Tratok Hospitality API authenticates requests with a per-provider
**API key**. Every request must carry the key in the `X-API-Key` HTTP
header.

```http
GET /api.php?endpoint=me HTTP/1.1
Host: hospitality.tratok.net
X-API-Key: 0f3b1cda…64-hex-chars…
Accept: application/json
```

There is **no** OAuth flow, no JWT issuance, no key in the URL.

> **Query-string keys are NOT accepted.** Earlier versions allowed
> `?api_key=…` as a fallback; that was removed because query strings leak
> into access logs, browser history, and `Referer` headers when a
> dashboard links to an upstream resource.

---

## Getting a key

1. Sign in at [hospitality.tratok.net](https://hospitality.tratok.net).
2. Open **Documentation → API** in the side navigation
   (`/documentation.php`).
3. Click **Generate API Key**. A 64-character hex key and a separate
   64-character secret are issued and displayed once.
4. Copy both into your secret manager. The dashboard will continue to
   display the key on subsequent visits, but the secret can only be
   viewed at generation/rotation time.

The key is bound to a single service-provider account and inherits that
account's listings (`properties`, `activities`, `restaurants`). You
cannot access another provider's data with your key.

---

## What the secret is for

Two things:

1. **Webhook signing** — every webhook payload Tratok sends you is signed
   with HMAC-SHA256 using a **per-webhook** secret (separate from the API
   key's secret), shown when you register the webhook. See
   [webhooks.md](webhooks.md).
2. **Future request signing** — reserved for an upcoming signed-request
   mode for high-risk endpoints (e.g. payouts). Today the API secret is
   issued but only used for forward-compatibility.

---

## Rotating your key

Two equivalent routes:

### From the dashboard

`Documentation → API → Regenerate` confirms once, then issues a new key +
secret. The old key stops working immediately.

### Programmatically

```bash
curl -X POST \
     -H "X-API-Key: $OLD_KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=api_key"
```

```json
{
  "success": true,
  "data": {
    "api_key": "newkey…",
    "api_secret": "newsecret…",
    "message": "API key regenerated. Update your integrations immediately."
  }
}
```

After the response, **the new key is active and the old key is dead.**
Plan rotations so your integrations swap atomically — there is no
overlap / grace period.

---

## Disabling / re-enabling

Toggling `api_enabled = 0` from the dashboard disables the key
immediately. Requests return:

```http
HTTP/1.1 401 Unauthorized
{ "success": false, "error": "Invalid or disabled API key." }
```

Toggle it back on from the same screen.

---

## Best practices

- **Never commit keys to source control.** Use environment variables,
  AWS Secrets Manager, Vault, GCP Secret Manager, or equivalent.
- **One key per integration** is fine — but if you have multiple
  integrations, request additional sub-accounts (each gets its own key)
  rather than sharing one key, so you can rotate or revoke independently.
  Email `api-support@tratok.net` to request sub-accounts.
- **Restrict by source IP** at your egress — Tratok does not currently
  enforce IP allowlists server-side; defense-in-depth at your end is
  recommended.
- **Treat the secret like a password.** It cannot be recovered, only
  re-issued via rotation.

---

## CORS

The API replies with:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, X-API-Key
```

so it can be called directly from a browser. **Do not.** A browser-side
call exposes your API key to anyone who views source. Always proxy
through a server-side component you control.
