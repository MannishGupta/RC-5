# PHP 8.2–8.5.12 full-tree audit (RC 4.5.6)

## Scope
- All application PHP under the web root (**282 files**)
- Excluded: `tenants/*/data`, `vendor/`, `.git/`

## Automated fixes applied
| Pattern | Count | Replacement |
|---------|------:|-------------|
| `curl_close()` | 11 | `unset($handle)` |
| `imagedestroy()` | 39 | `unset($image)` |
| `finfo_close()` | 14 | `unset($finfo)` |

Files touched include: `index.php`, `app/bootstrap.php`, `app/Optimizer.php`, `app/AiClient.php`, `app/GoogleSheetsClient.php`, `app/VehicleRegistry.php`, `app/VcardBuilder.php`, `status/lib.php`, `status/admin_backend.php`, `tools/pwa_icon.php`, `tools/export_vcf.php`, and others.

## Intentionally NOT applied (would break PHP 8.2 / 8.4 hosts)
- Pipe operator `|>`
- `array_first()` / `array_last()` without polyfill
- Asymmetric visibility requiring 8.4+ only code paths without guards

## Already clean
- No `(integer)/(boolean)/(double)/(binary)` casts
- No `create_function`, `mysql_*`, `__sleep`/`__wakeup`, `utf8_encode`/`utf8_decode`
- No backtick shell execution
- `declare(strict_types=1)` present on metals/choghadiya and many core modules

## Host action (outside this package)
Upgrade runtime to **PHP 8.5.12+** (or latest patched 8.2/8.3/8.4) for CVE-2026-109618 / 109619 when your host provides builds.

## Verification
`php -l` on all 282 application PHP files: **0 failures** (run on package build).
