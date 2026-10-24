# Delhi NCR & MCX Metals — cross-platform widgets

**Endpoint (per tenant host):** `https://{tenant-host}/metals.php`

| Mode | URL |
|------|-----|
| JSON API | `/metals.php?format=json` |
| Force refresh | `/metals.php?format=json&refresh=1` |
| Hindi web UI | `/metals.php?lang=hi` |
| PWA manifest | `/metals.php?manifest=1` |

## Stable JSON fields (do not rename)

- `status` — `LIVE` | `CACHED`
- `timestamp` — ISO-8601 (Asia/Kolkata)
- `formattedTime` — `h:i A`
- `fetchedAt` — human IST stamp
- `timezone` — `Asia/Kolkata`
- `corporate.text` / `corporate.url` / `corporate.logoUrl`
- `items.{gold,platinum,silver,copper,brass,zinc,steel}`:
  - `basePrice`, `gstPct`, `gstAmount`, `totalPrice`, `change`, `unit`, `pricePerKg`
- `order` — keys sorted high→low by ₹/kg

CORS: `Access-Control-Allow-Origin: *` on JSON responses.

## iOS Scriptable

```javascript
const url = "https://rc.arthsathi.com/metals.php?format=json"
const req = new Request(url)
const data = await req.loadJSON()
const gold = data.items.gold.totalPrice
```

## Android KWGT

Use WebFetch / JSON path, e.g.:

`$wg("https://rc.arthsathi.com/metals.php?format=json", json, .items.gold.totalPrice)$`

## PWA / Windows

Open `/metals.php` in Edge/Chrome → Install app / Add to home screen (`?manifest=1`).

Cache TTL: 10 minutes server-side (tenant `data/cache/` when multi-tenant).
