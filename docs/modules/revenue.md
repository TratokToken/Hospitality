# Module: `revenue`

Aggregated **revenue summary** across a date range. Backed by the
`revenue_summary` table, which is rolled up nightly from `bookings`.

This is the right endpoint for accounting/BI pulls — it's pre-
aggregated, so a "last 12 months" query is one cheap row-scan instead
of a `bookings` walk.

---

## `GET /api.php?endpoint=revenue`

### Query parameters

| Param | Type | Required | Default | Notes |
| --- | --- | --- | --- | --- |
| `from` | date | no | today − 30 days | `YYYY-MM-DD`, inclusive. |
| `to`   | date | no | today | `YYYY-MM-DD`, inclusive. |
| `type` | string | no | _(all)_ | One of `property`, `activity`, `restaurant`. |

### Response

```json
{
  "success": true,
  "data": {
    "period": { "from": "2026-04-19", "to": "2026-05-19" },
    "totals": {
      "gross_revenue":    18420.00,
      "net_revenue":      17139.00,
      "total_bookings":   62,
      "avg_per_booking":  276.44
    },
    "details": [
      {
        "period_date":     "2026-05-19",
        "source_type":     "property",
        "source_id":       24,
        "gross_revenue":   1080.00,
        "net_revenue":     1004.40,
        "booking_count":   3
      }
    ]
  }
}
```

### Field reference

**`period`** — echoes the resolved date window (after defaults).

**`totals`** — sums across the whole period after the `type` filter.

| Field | Notes |
| --- | --- |
| `gross_revenue` | Sum of fiat `total_price` for the period. |
| `net_revenue` | `gross_revenue` minus Tratok's platform fee (see below). |
| `total_bookings` | Count of confirmed-or-completed bookings rolled up. |
| `avg_per_booking` | `net_revenue / total_bookings`, rounded to 2 dp. `0` when there are no bookings (avoids divide-by-zero). |

**`details`** — one row per `(period_date, source_type, source_id)`.
Ordered by `period_date DESC`.

### Currency

`gross_revenue` and `net_revenue` are in **USD-equivalent** at booking
time. Bookings made in non-USD currencies are converted using the
`currencies.rateVsUSD` snapshot in force when the booking was made.
This makes cross-currency totals comparable but means the figure
doesn't match a raw sum of fiat `total_price` from `bookings` for
listings priced in other currencies.

### Platform fee

The fee deducted from `gross` to compute `net` is the standard Tratok
hospitality platform fee, currently **7%**. See your provider
agreement for the authoritative figure.

A future addition will expose the per-row fee explicitly.

### Errors

- `405` — method other than `GET`.

### Examples

```bash
# Last 30 days, all types
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=revenue"

# YTD, properties only
curl -H "X-API-Key: $KEY" \
     "https://hospitality.tratok.net/api.php?endpoint=revenue&from=2026-01-01&to=2026-12-31&type=property"
```

---

## When to use this vs `bookings`

| You want | Use |
| --- | --- |
| A daily/monthly revenue figure for accounting. | `revenue`. |
| A per-booking breakdown for invoicing. | `bookings`. |
| To detect newly confirmed bookings. | Webhooks. |
| To audit a specific settlement. | `bookings?id=…` for the row, including `tx_hash` and settlement amounts. |

`revenue` lags `bookings` by up to 24 hours — the rollup runs nightly.
For same-day totals, sum from `bookings?status=confirmed` client-side.
