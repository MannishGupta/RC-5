# PHP 8.5.12 full-tree audit (RC 5.2.0)

## Scope
- All application PHP under the web root (**274 files**)
- Excluded: `tenants/*/data`, `vendor/`, `.git/`

## Automated / retained fixes
| Pattern | Status | Replacement |
|---------|--------|-------------|
| `curl_close()` | 0 remaining | `unset($handle)` |
| `imagedestroy()` | 0 remaining | `unset($image)` |
| `finfo_close()` | 0 remaining | `unset($finfo)` |
| `(integer)/(boolean)/(double)` casts | 0 remaining | native casts |
| `create_function` / `mysql_*` / `ereg` | 0 remaining | — |
| backtick shell execution | 0 remaining | — |

## 5.2.0 changes
- Runtime gate: `PHP_VERSION_ID >= 80512`
- Composer platform: `php: 8.5.12`
- `Optimizer::optimizeImageFile`: pre-flight memory estimate + guarded `imagecreatetruecolor`
- `APP_VERSION` 5.2.0 / `APP_PHP_MIN` 8.5.12

## Intentionally NOT applied
- Pipe operator `|>` across entire tree (incremental only)
- `declare(strict_types=1)` on third-party `phpqrcode` and large view templates (coercion risk)

## Verification
`php -l` on application PHP files on package build host.
