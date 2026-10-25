# Resource Centre (RC) 3.0.3

Multi-tenant PHP **8.5.0+** (recommended 8.5.12+). Developer: **Arthsathi Limited**.

| Doc | Topic |
|-----|--------|
| [docs/DEPLOY.md](docs/DEPLOY.md) | Never overwrite `tenants/` |
| [docs/LAUNCH_OPS.md](docs/LAUNCH_OPS.md) | Key rotation & go-live |
| [docs/DATA_AND_TENANTS.md](docs/DATA_AND_TENANTS.md) | Data & optimizer |
| [docs/THEME_COMPLIANCE.md](docs/THEME_COMPLIANCE.md) | Theme & DPDP |
| [docs/WHATSNEW.md](docs/WHATSNEW.md) | Highlights |

**Never upload a code package over `tenants/`.**


## PHP baseline

- Requires **PHP 8.5.12+** (hard gate in `index.php` / `app/php85_runtime.php`).
- Application code targets 8.5 idioms: `|>` pipe operator, `array_first` / `array_last`, `str_contains` / `str_starts_with`, first-class callables (`strtolower(...)`).

## PHP 8.5 coding standard (application code)

Version gate: **PHP 8.5.12+** (`PHP_VERSION_ID >= 80512`). Do not add polyfills for 8.3/8.4.

| Prefer | Avoid |
|--------|--------|
| `str_contains` / `str_starts_with` / `str_ends_with` | `strpos(...) !== false` / `=== 0` |
| `array_first` / `array_last` | `reset` / `end` / `$a[array_key_first($a)]` |
| `$x \|> trim(...) \|> strtolower(...)` | `strtolower(trim($x))` |
| `array_map(strtolower(...), $a)` | `array_map('strtolower', $a)` |
| `match (true) { ... }` | long `switch` when each arm returns |
| native typed properties / `readonly` | untyped public fields on new classes |

Third-party trees (`app/lib/phpqrcode`, etc.) are left upstream-style on purpose.
