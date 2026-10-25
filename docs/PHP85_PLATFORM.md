# Resource Centre 5.2 — PHP 8.5.12 platform

## Requirement
- **Minimum / required:** PHP **8.5.12**
- Runtime gate: `app/php85_runtime.php` (`PHP_VERSION_ID < 80512` → HTTP 503)
- Composer: `"php": "^8.5.12"`

## What 5.2 includes
1. Hard floor raised from 8.5.0 → **8.5.12** (security baseline CVE-2026-109618 / CVE-2026-109619)
2. Deprecation cleanup retained: `curl_close`, `imagedestroy`, `finfo_close` → `unset`
3. Native `array_first` / `array_last` only (no polyfills)
4. `Optimizer::optimizeImageFile` memory-safe allocations under 256M limits
5. `APP_VERSION` = **5.2.0**, `APP_PHP_MIN` = **8.5.12**

## Intentionally limited
Blanket conversion of every nested call to `|>` was **not** applied across the full tree — high regression risk with no behavioural gain. Prefer incremental pipelines in new code.

## Deploy
1. Confirm every host reports `php -v` → **8.5.12** (or newer 8.5.x)
2. Upload package without overwriting `tenants/*/data`
3. Hard refresh; footer shows **5.2.0**
