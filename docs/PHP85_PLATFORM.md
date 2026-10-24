# Resource Centre 5.0 — PHP 8.5.12 platform

## Requirement
- **Minimum:** PHP 8.5.0
- **Recommended:** PHP 8.5.12+ (security release for CVE-2026-109618 / CVE-2026-109619)

Runtime gate: `app/php85_runtime.php` (loaded from `app/bootstrap.php` and standalone endpoints).

## What 5.0 includes
1. Deprecation cleanup (4.5.6 pass): `curl_close`, `imagedestroy`, `finfo_close` → `unset`
2. `array_first` / `array_last` polyfills when not native
3. Safe `reset($arr)` → `array_first` where applicable
4. CI matrix locked to PHP 8.5
5. `APP_PHP_MIN` = `8.5.12`

## Intentionally limited
Blanket conversion of every nested call to `|>` was **not** applied across 282 files in one release — high regression risk with no behavioural gain. Prefer incremental pipelines in new code.

## Deploy
1. Confirm every host reports `php -v` → 8.5.12 (or newer 8.5.x)
2. Upload RC-5.0.0-COMPLETE.zip without overwriting `tenants/*/data`
3. Hard refresh; footer shows **5.0.0**
