# Module: `rooms`

Read-only access to the **room types** within each accommodation. A room
is the entity that carries per-date pricing and per-date inventory (see
[pricing.md](pricing.md) and [inventory.md](inventory.md)).

Room creation and content edits go through `/room_management.php` in
the portal.

---

## `GET /api.php?endpoint=rooms&property_id=<int>`

List all rooms in a specific property, ordered by `name ASC`.

### Query parameters

| Param | Type | Required | Notes |
| --- | --- | --- | --- |
| `property_id` | integer | yes | Property must be owned by you. |

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "name": "Deluxe Suite",
      "description": "Spacious suite with sea view, king bed, marble bath.",
      "price": 199.00,
      "currency": "USD",
      "numberOfRooms": 5,
      "maxCapacity": 3,
      "image": "uploads/rooms/room_101.jpg",
      "deactivated": false,
      "created_at": "2025-01-21 11:15:00"
    }
  ]
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `id` | integer | Pass to `pricing` / `inventory` / `?endpoint=rooms&id=…`. |
| `name` | string | Provider-edited, may be translated. |
| `description` | string | Long-form description. |
| `price` | float | **Default base price** in `currency`. Used when no `room_pricing` row exists for the date being queried. |
| `currency` | string | ISO 4217. Pricing writes assume the same currency — there is no per-date currency override. |
| `numberOfRooms` | integer | How many physical rooms of this type exist. Caps `available` in inventory writes. |
| `maxCapacity` | integer | Guests per room. |
| `image` | string | JPG/PNG path under hospitality.tratok.net. |
| `deactivated` | boolean | Provider-toggled. Deactivated rooms hide on tratok.net regardless of inventory. |
| `created_at` | string | UTC. |

Note: paginated only by `property_id`; `page`/`limit` are ignored
because room counts per property are bounded.

### Errors

- `400` — `property_id` missing.
- `404` — property not yours / doesn't exist.

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=rooms&property_id=24"
```

---

## `GET /api.php?endpoint=rooms&id=<int>`

Fetch one room with the full row (includes raw provider-edited fields
not surfaced in the list view).

### Query parameters

| Param | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | integer | yes | Room id; must belong to a property you own. |

### Response

```json
{
  "success": true,
  "data": {
    "id": 101,
    "property_id": 24,
    "name": "Deluxe Suite",
    "description": "Spacious suite with sea view, king bed, marble bath.",
    "price": 199.00,
    "currency": "USD",
    "numberOfRooms": 5,
    "maxCapacity": 3,
    "amenities": "Wi-Fi, mini-bar, sea view, balcony",
    "image": "uploads/rooms/room_101.jpg",
    "deactivated": false,
    "created_at": "2025-01-21 11:15:00",
    "updated_at": "2026-03-08 16:00:00"
  }
}
```

Most fields are documented above. Additional:

| Field | Type | Notes |
| --- | --- | --- |
| `property_id` | integer | Parent property id; useful when you're holding only a room id. |
| `amenities` | string | Free-text, comma-separated (provider convention, not enforced). |
| `updated_at` | string \| null | UTC. |

### Errors

- `400` — neither `id` nor `property_id` provided.
- `404` — room not found / not yours.

### Examples

```bash
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=rooms&id=101"
```
