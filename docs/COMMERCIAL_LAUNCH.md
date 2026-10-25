# Resource Centre 5.2.2 — Commercial launch notes

## Hosting
- **PHP 8.5.12+** (hard gate), IIS (`web.config`) or Apache (`.htaccess`)
- Timezone: Asia/Kolkata · Locales: en-IN / hi-IN
- Upload limits: root web.config 50MB body + `.user.ini` (PHP) + `app/UploadLimits.php`

## Self-hosted UI stack
Shell libraries load from `/assets/vendor/` and `/assets/fonts/` only.  
See `docs/ASSET_MANIFEST.md`. Bootstrap is limited to the Status module.

## Do not deploy over live tenant data
- Never overwrite `tenants/*/data/` with sample data from the zip
- Keep `data/auth_config.php` out of public repos; rotate defaults on first production install

## Verify after deploy
1. `/health.php` → JSON ok, version **5.2.2**
2. Login → dashboard; themes readable; sidebar footer one row
3. Team directory page size looks dense (no large blank bands)
4. Media Kit / image optimise — no 256M OOM on typical photos
5. `/status/` if licensed
6. `/robots.php` and `/llms.txt` reachable
7. Hard-refresh assets

## Support
Arthsathi Limited — https://arthsathi.com
