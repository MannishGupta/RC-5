# 5.0.1 — Critical-path modernization

Files upgraded with PHP 8.5 idioms (beyond the 5.0.0 platform gate):

| File | Changes |
|------|---------|
| `health.php` | Clean JSON probe; `JSON_THROW_ON_ERROR`; includes `php` version |
| `media_serve.php` | `match` MIME map; `never` deny helper; master `?m=` + tenant `?f=` paths; SVG CSP |
| `app/MasterDirectory.php` | `array_first()` for logo URL picks |
| `app/VehicleCatalog.php` | strict types + version header |
| `app/PathJail.php` | version header |
| `app/AiClient.php` | version header (curl cleanup from 4.5.6 retained) |
| `app/GoogleSheetsClient.php` | version header |
| `metals.php` / `choghadiya.php` | 5.0.1 markers; Scriptable contracts unchanged |

Pipe operator (`|>`) was not bulk-applied: production safety over style. Prefer `|>` in new code on 8.5.12 hosts.
