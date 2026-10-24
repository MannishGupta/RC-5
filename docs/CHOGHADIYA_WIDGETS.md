# Choghadiya — cross-platform widgets

**Endpoint:** `https://{tenant-host}/choghadiya.php`

| Mode | URL |
|------|-----|
| JSON API | `/choghadiya.php?format=json` |
| Hindi UI | `/choghadiya.php?lang=hi` |
| PWA | `/choghadiya.php?manifest=1` |
| Embed | `/choghadiya.php?embed=1` |

## JSON fields

- `status`, `timestamp`, `formattedTime`, `timezone` (`Asia/Kolkata`)
- `tithi` · `rahu` · `sun` · `current`
- `day[]` / `night[]` — `{name,nameHi,kind,start,end,isNow}`
- `corporate` — branding

CORS: `Access-Control-Allow-Origin: *`

## Scriptable / KWGT

```
…/choghadiya.php?format=json  →  .current.name  ·  .rahu.label  ·  .tithi.name
```
