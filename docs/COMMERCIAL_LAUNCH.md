# Resource Centre 4.0.0 — Commercial launch notes

## Hosting
- PHP 8.2–8.4, IIS (web.config) or Apache
- Timezone: Asia/Kolkata · Locales: en-IN / hi-IN
- Upload limits: root web.config 50MB + `.user.ini` (PHP) + `app/UploadLimits.php`

## Do not deploy over live tenant data
- Never overwrite `tenants/*/data/` with sample data from the zip
- Keep `data/auth_config.php` out of public repos; rotate defaults on first production install

## Verify after deploy
1. `/health.php` → JSON ok
2. Login → dashboard sidebar counts + Insights (Numero)
3. `/status/` logos (dark plate for white SVGs)
4. Media Kit share → absolute https URL in WhatsApp
5. `/robots.php` and `/llms.txt` reachable
6. Hard-refresh assets (`?v=4.0.0`)

## Support
Arthsathi Limited — https://arthsathi.com
