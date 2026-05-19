# Module: `me` + `api_key`

Account-level endpoints that don't depend on any listing id.

---

## `GET /api.php?endpoint=me`

Returns the authenticated provider's profile, plus the rate-limit budget
in force for this API key. Used as a connectivity / health check.

### Request

| Param | Type | Required | Notes |
| --- | --- | --- | --- |
| _(none)_ | | | |

### Response

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Smith",
    "email": "john@hotel.com",
    "company": "Grand Hotel Group",
    "country": "United Kingdom",
    "city": "London",
    "email_verified": true,
    "api_rate_limit": 100,
    "member_since": "2025-01-15 10:30:00"
  }
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `id` | integer | Internal provider id — appears as `owner` on properties/activities/restaurants. |
| `name` | string | `first_name last_name`, space-joined. |
| `email` | string | The provider's login email. |
| `company` | string | Free-text company name. |
| `country` | string | ISO English country name (e.g. "United Kingdom", not "GB"). |
| `city` | string | Free-text. |
| `email_verified` | boolean | False until the verification email link is clicked. Unverified providers cannot publish listings. |
| `api_rate_limit` | integer | Requests-per-minute budget for this key. |
| `member_since` | string | UTC `YYYY-MM-DD HH:MM:SS` of account creation. |

### Errors

- `401` — invalid/disabled key.
- `405` — method other than `GET`.

### Examples

```bash
curl -H "X-API-Key: $TRATOK_API_KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=me"
```

---

## `POST /api.php?endpoint=api_key`  <a id="rotate-api-key"></a>

Rotates the calling key. Both the API key and the API secret are
replaced atomically; the old key stops working immediately.

### Request

| Param | Type | Required | Notes |
| --- | --- | --- | --- |
| _(none)_ | | | The call itself is the rotation. |

### Response

```json
{
  "success": true,
  "data": {
    "api_key":    "newkey…64-hex-chars…",
    "api_secret": "newsecret…128-hex-chars…",
    "message":    "API key regenerated. Update your integrations immediately."
  }
}
```

### Errors

- `401` — current key invalid/disabled.
- `405` — method other than `POST`.

### Best practices

- **Don't retry** this endpoint blindly. If the response is lost
  mid-flight, you've already rotated; the safest recovery is to log in
  to the portal and rotate again, so the dashboard-displayed key
  matches what you're storing.
- Run rotations from a single coordinator so you don't have multiple
  workers all rotating in parallel.
- Update secret managers FIRST, then call this endpoint, then redeploy
  consumers. The new key is live immediately.

### Examples

```bash
curl -X POST \
     -H "X-API-Key: $OLD_KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=api_key"
```
